<?php

namespace Glubschi\Seat\QuickSell\Jobs;

use Glubschi\Seat\QuickSell\Markets\OrderBooks;
use Seat\Eseye\Exceptions\RequestFailedException;
use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Reads the whole market of an Upwell structure with one character's token and caches it, 10 best orders per
 * side and item.
 *
 * A SeAT authenticated character job (AbstractAuthCharacterJob): SeAT refreshes the token, checks the scope
 * and keeps it on the "characters" queue. Like SeAT's own Citadel job, a 403 (the character may not use the
 * structure's market) is not an error: the next character that might have access is tried, and when none is
 * left the structure is remembered as not readable for 5 minutes.
 */
class StructureOrders extends AbstractAuthCharacterJob
{
    public const SCOPE = 'esi-markets.structure_markets.v1';

    protected $method = 'get';

    protected $endpoint = '/markets/structures/{structure_id}/';

    protected string $compatibility_date = '2025-07-20';

    protected $scope = self::SCOPE;

    protected $tags = ['character', 'market', 'quicksell'];

    protected $page = 1;

    /**
     * @param  int[]  $nextCharacterIds  characters to try after this one if it has no access
     */
    public function __construct(RefreshToken $token, protected int $structureId, protected array $nextCharacterIds = [])
    {
        parent::__construct($token);
    }

    public function tags(): array
    {
        return array_merge(parent::tags(), ['structure:' . $this->structureId]);
    }

    public function handle(): void
    {
        parent::handle();
        if ($this->batch()?->cancelled()) {
            return;
        }

        $orders = [];
        $ttl = OrderBooks::DEFAULT_TTL;
        try {
            while (true) {
                $response = $this->retrieve(['structure_id' => $this->structureId]);
                array_push($orders, ...$response->getBody());
                $ttl = min($ttl, OrderBooks::ttl($response->getHeaderLine('Expires') ?: null));
                if (! $this->nextPage($response->getPagesCount())) {
                    break;
                }
            }
        } catch (RequestFailedException $e) {
            if ($e->getEsiResponse()->getErrorCode() !== 403) {
                throw $e;
            }
            $this->tryNextCharacter();

            return;
        }

        OrderBooks::putStructure($this->structureId, OrderBooks::reduce($orders)[$this->structureId] ?? [], $ttl);
    }

    private function tryNextCharacter(): void
    {
        $next = self::firstToken($this->nextCharacterIds);
        if ($next === null) {
            OrderBooks::markDenied($this->structureId);

            return;
        }
        $rest = array_values(array_diff($this->nextCharacterIds, [$next->character_id]));
        $job = new self($next, $this->structureId, $rest);
        $this->batch() ? $this->batch()->add([$job]) : dispatch($job);
    }

    /**
     * The first of the characters whose token carries the structure market scope. Characters without it are
     * skipped here, because SeAT's scope check would silently drop the job (and a batch would never finish).
     */
    public static function firstToken(array $characterIds): ?RefreshToken
    {
        foreach ($characterIds as $id) {
            $token = RefreshToken::find($id);
            if ($token !== null && $token->hasScope(self::SCOPE)) {
                return $token;
            }
        }

        return null;
    }
}
