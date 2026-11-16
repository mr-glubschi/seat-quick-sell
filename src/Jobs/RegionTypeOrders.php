<?php

namespace Glubschi\Seat\QuickSell\Jobs;

use Glubschi\Seat\QuickSell\Markets\OrderBooks;
use Seat\Eveapi\Jobs\EsiBase;

/**
 * Reads the public market orders of one item in one region and caches the books of the given stations.
 *
 * A SeAT ESI job (EsiBase): SeAT's own ESI client, rate limit and error handling, on the "public" queue like
 * SeAT's market jobs. One request per page of /markets/{region_id}/orders/?type_id=, usually one page. Used for
 * NPC stations and for Jita; when both are in the same region one job serves both.
 */
class RegionTypeOrders extends EsiBase
{
    protected $method = 'get';

    protected $endpoint = '/markets/{region_id}/orders/';

    protected string $compatibility_date = '2025-07-20';

    protected $tags = ['public', 'market', 'quicksell'];

    protected $page = 1;

    /**
     * @param  int[]  $locationIds  stations whose books to cache
     */
    public function __construct(
        protected int $regionId,
        protected int $typeId,
        protected array $locationIds,
    ) {
        parent::__construct();
        $this->query_string = ['type_id' => $typeId, 'order_type' => 'all'];
    }

    public function tags(): array
    {
        return array_merge(parent::tags(), ['region:' . $this->regionId, 'type:' . $this->typeId]);
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $orders = [];
        $ttl = OrderBooks::DEFAULT_TTL;
        while (true) {
            $response = $this->retrieve(['region_id' => $this->regionId]);
            array_push($orders, ...$response->getBody());
            $ttl = min($ttl, OrderBooks::ttl($response->getHeaderLine('Expires') ?: null));
            if (! $this->nextPage($response->getPagesCount())) {
                break;
            }
        }

        $books = OrderBooks::reduce($orders, $this->locationIds);
        foreach ($this->locationIds as $locationId) {
            // An empty book is information too: nobody trades the item there.
            OrderBooks::putStation($locationId, $this->typeId, $books[$locationId][$this->typeId] ?? ['sell' => [], 'buy' => []], $ttl);
        }
    }
}
