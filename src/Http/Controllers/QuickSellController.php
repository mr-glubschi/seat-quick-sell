<?php

namespace Glubschi\Seat\QuickSell\Http\Controllers;

use Glubschi\Seat\QuickSell\Items\Materials;
use Glubschi\Seat\QuickSell\Items\Parser;
use Glubschi\Seat\QuickSell\Markets\Books;
use Glubschi\Seat\QuickSell\Markets\Market;
use Glubschi\Seat\QuickSell\Markets\Markets;
use Glubschi\Seat\QuickSell\Markets\OrderBooks;
use Glubschi\Seat\QuickSell\Models\MarketFee;
use Glubschi\Seat\QuickSell\Pricing\Compare;
use Glubschi\Seat\QuickSell\Pricing\Fees;
use Glubschi\Seat\QuickSell\Pricing\Quote;
use Glubschi\Seat\QuickSell\Pricing\Reprocess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Seat\Web\Http\Controllers\Controller;

/**
 * The Quick Sell page and its JSON endpoints.
 *
 * Flow: the page posts the pasted items to quote(). When order books are missing from the cache, SeAT jobs are
 * dispatched as batches and their IDs returned; the page polls status() and posts again with no_fetch once the
 * batches are done. Everything works on the logged-in user's own characters only.
 */
class QuickSellController extends Controller
{
    /** Item kinds per quote; each one can take an ESI request for the market and one for Jita (plus materials). */
    public const MAX_TYPES = 200;

    public const LAST_MARKET_SETTING = 'glubschi.quicksell.last_market';

    private const SESSION_BATCHES = 'glubschi.quicksell.batches';

    public function index()
    {
        $characters = $this->characters();

        // Preselected: the last used market, else the first place a character is docked at.
        $preselect = $this->lastMarket();
        if ($preselect === null && ($first = Markets::docked($characters)[0] ?? null)) {
            $preselect = $first['market']->toArray();
        }

        return view('quicksell::index', [
            'characters' => $characters,
            'mainId' => (int) auth()->user()->main_character_id,
            'preselect' => $preselect,
        ]);
    }

    /** Market search for the select box: without a term the docked places, the last market and the hubs. */
    public function markets(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 3) {
            return response()->json(['results' => $this->defaultMarkets($this->characters())]);
        }

        $results = array_map(fn (Market $m) => ['id' => $m->id(), 'text' => $m->name], Markets::search($term));

        return response()->json(['results' => $results]);
    }

    public function quote(Request $request): JsonResponse
    {
        $request->validate([
            'market' => 'required|string|max:40',
            'items' => 'required|string|max:200000',
            'character_id' => 'nullable|integer',
            'no_fetch' => 'nullable|boolean',
            'fees' => 'nullable|array',
            'fees.*' => 'nullable|numeric|min:0|max:100',
        ]);

        $market = Markets::find($request->input('market'));
        if ($market === null) {
            return response()->json(['message' => trans('quicksell::global.error_market')], 422);
        }

        $parsed = Parser::parse($request->input('items'));
        if ($parsed['items'] === []) {
            return response()->json(['message' => trans('quicksell::global.error_no_items'), 'unknown' => $parsed['unknown']], 422);
        }
        if (count($parsed['items']) > self::MAX_TYPES) {
            return response()->json(['message' => trans('quicksell::global.error_too_many', ['max' => self::MAX_TYPES])], 422);
        }

        setting([self::LAST_MARKET_SETTING, $market->id()]);

        $characters = $this->characters();
        $docked = $this->dockedAt($market, $characters);
        $typeIds = array_column($parsed['items'], 'type_id');
        $reprocessing = Materials::for($typeIds);
        // The market needs the items and their materials, Jita only the items.
        $marketTypeIds = array_values(array_unique(array_merge($typeIds, Materials::materialIds($reprocessing))));

        $books = Books::cached($market, $marketTypeIds, $typeIds);
        if (! $books['complete'] && ! $request->boolean('no_fetch')) {
            $order = array_values(array_unique(array_merge($docked, array_keys($characters))));
            $batches = $this->dispatch(Books::jobs($market, $marketTypeIds, $order, $typeIds));
            if ($batches !== []) {
                return response()->json(['status' => 'pending', 'batches' => $batches]);
            }
            $books = Books::cached($market, $marketTypeIds, $typeIds);
        }

        // Fees as entered; they become this market's last values.
        $fees = Fees::from($market->isStructure(), (array) $request->input('fees', []));
        MarketFee::remember(auth()->user()->id, $market->locationId, $fees);

        $sellerId = $this->seller($request->input('character_id'), $characters);
        $skills = $this->skills($sellerId, $reprocessing);
        [$ownOrderIds, $ownSellTypes] = $this->ownOrders(array_keys($characters), $market, $typeIds);

        $rows = Quote::windowOrder(Quote::rows($parsed['items'], $books['market'], $books['jita'], $ownOrderIds, $ownSellTypes));
        $compare = Compare::run($rows, $books['market'], $reprocessing, $skills, $fees, $ownOrderIds);
        $rows = $compare['rows'];
        // The copy button takes the items that are best put on the market.
        $marketRows = array_values(array_filter($rows, fn ($r) => $r['best'] === 'market'));

        return response()->json([
            'status' => 'done',
            'market' => $market->toArray() + [
                'denied' => $market->isStructure() && OrderBooks::isDenied($market->locationId),
            ],
            'seller' => ['id' => $sellerId, 'name' => $characters[$sellerId] ?? null],
            'skills' => $this->skillNames($skills),
            'rows' => array_map([$this, 'formatRow'], $rows),
            'unknown' => $parsed['unknown'],
            'fees' => $fees->toArray(),
            'totals' => array_map(fn ($t) => ['gross' => number($t['gross'], 0), 'net' => number($t['net'], 0), 'raw' => $t['net']], $compare['totals']),
            'import_text' => Quote::importText($marketRows),
            'import_text_all' => Quote::importText($rows),
            'import_count' => count($marketRows),
        ]);
    }

    /** Progress of the batches started by quote(); only batches of this session can be read. */
    public function status(Request $request): JsonResponse
    {
        $own = session(self::SESSION_BATCHES, []);
        $ids = array_values(array_intersect((array) $request->query('batches', []), $own));
        $total = 0;
        $done = 0;
        $finished = true;
        foreach ($ids as $id) {
            $batch = Bus::findBatch($id);
            if ($batch === null) {
                continue;
            }
            $total += $batch->totalJobs;
            $done += $batch->processedJobs() + $batch->failedJobs;
            $finished = $finished && ($batch->finished() || $batch->cancelled() || $batch->pendingJobs <= $batch->failedJobs);
        }

        return response()->json(['finished' => $finished, 'total' => $total, 'done' => min($done, $total)]);
    }

    /** The last fees used at a market, or the game's starting values when there are none yet. */
    public function fees(Request $request): JsonResponse
    {
        $market = Markets::find((string) $request->query('market', ''));
        if ($market === null) {
            return response()->json(['message' => trans('quicksell::global.error_market')], 422);
        }
        $saved = MarketFee::savedFor(auth()->user()->id, $market->locationId);

        return response()->json([
            'fees' => Fees::from($market->isStructure(), $saved)->toArray(),
            'saved' => $saved !== [],
        ]);
    }

    /** Forgets the fees saved for a market and returns the starting values. */
    public function resetFees(Request $request): JsonResponse
    {
        $request->validate(['market' => 'required|string|max:40']);
        $market = Markets::find($request->input('market'));
        if ($market === null) {
            return response()->json(['message' => trans('quicksell::global.error_market')], 422);
        }
        MarketFee::where(['user_id' => auth()->user()->id, 'location_id' => $market->locationId])->delete();

        return response()->json(['fees' => Fees::defaults($market->isStructure()), 'saved' => false]);
    }

    /** @return array<int, string> names of the user's characters by ID */
    private function characters(): array
    {
        return auth()->user()->characters()->orderBy('character_infos.name')->pluck('character_infos.name', 'character_infos.character_id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])->all();
    }

    private function lastMarket(): ?array
    {
        $id = setting(self::LAST_MARKET_SETTING);

        return is_string($id) ? Markets::find($id)?->toArray() : null;
    }

    /** Select box groups: where the characters are docked, the last used market, the trade hubs. */
    private function defaultMarkets(array $characters): array
    {
        $groups = [];
        $docked = array_map(fn ($p) => [
            'id' => $p['market']->id(),
            'text' => $p['market']->name . ' (' . implode(', ', $p['characters']) . ')',
        ], Markets::docked($characters));
        if ($docked !== []) {
            $groups[] = ['text' => trans('quicksell::global.group_docked'), 'children' => $docked];
        }
        if ($last = $this->lastMarket()) {
            $groups[] = ['text' => trans('quicksell::global.group_last'), 'children' => [['id' => $last['id'], 'text' => $last['name']]]];
        }
        $groups[] = ['text' => trans('quicksell::global.group_hubs'), 'children' => array_map(
            fn (Market $m) => ['id' => $m->id(), 'text' => $m->name], Markets::hubs())];

        return $groups;
    }

    /** @return int[] the user's characters docked at the market */
    private function dockedAt(Market $market, array $characters): array
    {
        foreach (Markets::docked($characters) as $place) {
            if ($place['market']->id() === $market->id()) {
                return $place['character_ids'];
            }
        }

        return [];
    }

    /** The character whose skills count for reprocessing: the chosen one, else the main character. */
    private function seller(mixed $chosen, array $characters): ?int
    {
        if ($chosen !== null && $chosen !== '' && isset($characters[(int) $chosen])) {
            return (int) $chosen;
        }
        $main = (int) auth()->user()->main_character_id;
        if ($main && isset($characters[$main])) {
            return $main;
        }

        return array_key_first($characters);
    }

    /** @return array<int, int> the character's levels of the reprocessing skills these items use, by skill ID */
    private function skills(?int $characterId, array $reprocessing): array
    {
        $ids = [Reprocess::REPROCESSING, Reprocess::REPROCESSING_EFFICIENCY, Reprocess::SCRAPMETAL_PROCESSING];
        foreach ($reprocessing as $info) {
            if ($info['ore_skill']) {
                $ids[] = $info['ore_skill'];
            }
        }
        $levels = array_fill_keys(array_unique($ids), 0);
        if ($characterId !== null) {
            $rows = DB::table('character_skills')->where('character_id', $characterId)
                ->whereIn('skill_id', array_keys($levels))->pluck('active_skill_level', 'skill_id');
            foreach ($rows as $id => $level) {
                $levels[(int) $id] = (int) $level;
            }
        }

        return $levels;
    }

    /** @return array<int, array{name: string, level: int}> */
    private function skillNames(array $skills): array
    {
        $names = DB::table('invTypes')->whereIn('typeID', array_keys($skills))->pluck('typeName', 'typeID');
        $out = [];
        foreach ($skills as $id => $level) {
            $out[] = ['name' => $names[$id] ?? (string) $id, 'level' => $level];
        }

        return $out;
    }

    /**
     * The user's open orders for these items, from SeAT's character orders: all order IDs (ignored when
     * finding the best price, at any market) and the items with a sell order at this market (marked).
     */
    private function ownOrders(array $characterIds, Market $market, array $typeIds): array
    {
        $orders = DB::table('character_orders')->whereIn('character_id', $characterIds)
            ->whereIn('type_id', $typeIds)->where('state', 'active')
            ->get(['order_id', 'type_id', 'location_id', 'is_buy_order']);
        $ids = [];
        $sellTypes = [];
        foreach ($orders as $o) {
            $ids[(int) $o->order_id] = true;
            if (! $o->is_buy_order && (int) $o->location_id === $market->locationId) {
                $sellTypes[(int) $o->type_id] = true;
            }
        }

        return [$ids, $sellTypes];
    }

    /** @return string[] IDs of the dispatched batches, remembered in the session for status() */
    private function dispatch(array $jobs): array
    {
        $ids = [];
        foreach (['public' => $jobs['public'], 'characters' => $jobs['characters']] as $queue => $list) {
            if ($list === []) {
                continue;
            }
            // SeAT's own batches set the queue on the batch (a batch pushes all its jobs to one queue).
            $ids[] = Bus::batch($list)->name('Quick Sell')->onQueue($queue)->allowFailures()->dispatch()->id;
        }
        if ($ids !== []) {
            session([self::SESSION_BATCHES => array_slice(array_merge(session(self::SESSION_BATCHES, []), $ids), -20)]);
        }

        return $ids;
    }

    private function formatRow(array $row): array
    {
        $fmt = fn (?float $v) => $v === null ? null : number($v, 2);

        return $row + [
            // SeAT's image helper: the item's icon from CCP's image server, as on SeAT's own pages.
            'icon' => img('types', 'icon', $row['type_id'], 32, ['class' => 'img-circle eve-icon small-icon'], false),
            'fmt' => [
                'station_sell' => $fmt($row['station_sell']),
                'station_buy' => $fmt($row['station_buy']),
                'jita_sell' => $fmt($row['jita_sell']),
                'jita_buy' => $fmt($row['jita_buy']),
                'price' => $fmt($row['price']),
                'value' => $fmt($row['value']),
                'quantity' => number($row['quantity'], 0),
                'vs_jita_pct' => $row['vs_jita_pct'] === null ? null : number($row['vs_jita_pct'], 1),
            ],
            'ways_fmt' => array_map(fn ($w) => $w === null ? null : [
                'net' => number($w['net'], 0),
                'per_unit' => number($w['per_unit'], 2),
                'sold' => isset($w['sold']) ? number($w['sold'], 0) : null,
                'yield' => isset($w['yield']) ? number($w['yield'] * 100, 1) : null,
            ], $row['ways']),
        ];
    }
}
