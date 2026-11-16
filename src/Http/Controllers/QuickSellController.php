<?php

namespace Glubschi\Seat\QuickSell\Http\Controllers;

use Glubschi\Seat\QuickSell\Items\Parser;
use Glubschi\Seat\QuickSell\Markets\Books;
use Glubschi\Seat\QuickSell\Markets\Market;
use Glubschi\Seat\QuickSell\Markets\Markets;
use Glubschi\Seat\QuickSell\Markets\OrderBooks;
use Glubschi\Seat\QuickSell\Models\MarketFee;
use Glubschi\Seat\QuickSell\Pricing\Fees;
use Glubschi\Seat\QuickSell\Pricing\Quote;
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
    /** Item kinds per quote; each one can take an ESI request for the market and one for Jita. */
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

        $books = Books::cached($market, $typeIds);
        if (! $books['complete'] && ! $request->boolean('no_fetch')) {
            $order = array_values(array_unique(array_merge($docked, array_keys($characters))));
            $batches = $this->dispatch(Books::jobs($market, $typeIds, $order));
            if ($batches !== []) {
                return response()->json(['status' => 'pending', 'batches' => $batches]);
            }
            $books = Books::cached($market, $typeIds);
        }

        $sellerId = $this->seller($request->input('character_id'), $docked, $characters);
        [$ownOrderIds, $ownSellTypes] = $this->ownOrders(array_keys($characters), $market, $typeIds);
        $rows = Quote::rows($parsed['items'], $books['market'], $books['jita'], $ownOrderIds, $ownSellTypes);
        $fees = $this->fees($market, $sellerId);
        $totals = $fees->totals(array_values(array_filter(array_column($rows, 'value'))));

        return response()->json([
            'status' => 'done',
            'market' => $market->toArray() + [
                'denied' => $market->isStructure() && OrderBooks::isDenied($market->locationId),
            ],
            'seller' => ['id' => $sellerId, 'name' => $characters[$sellerId] ?? null, 'docked' => in_array($sellerId, $docked, true)],
            'rows' => array_map([$this, 'formatRow'], $rows),
            'unknown' => $parsed['unknown'],
            'fees' => [
                'broker_pct' => $fees->brokerPct,
                'scc_pct' => $fees->sccPct,
                'sales_tax_pct' => $fees->salesTaxPct,
                'safety_pct' => $fees->safetyPct,
                'saved' => MarketFee::savedFor(auth()->user()->id, $market->locationId) !== [],
            ],
            'totals' => array_map(fn ($v) => ['raw' => $v, 'fmt' => number($v, 2)], $totals),
            'import_text' => Quote::importText($rows),
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

    /** Saves the fees the user corrected for a market, or forgets them (reset). */
    public function saveFees(Request $request): JsonResponse
    {
        $request->validate([
            'market' => 'required|string|max:40',
            'reset' => 'nullable|boolean',
            'broker_pct' => 'nullable|numeric|min:0|max:100',
            'scc_pct' => 'nullable|numeric|min:0|max:100',
            'safety_pct' => 'nullable|numeric|min:0|max:100',
        ]);
        $market = Markets::find($request->input('market'));
        if ($market === null) {
            return response()->json(['message' => trans('quicksell::global.error_market')], 422);
        }

        $key = ['user_id' => auth()->user()->id, 'location_id' => $market->locationId];
        if ($request->boolean('reset')) {
            MarketFee::where($key)->delete();
        } else {
            // An empty field means "use the default" (stored as null).
            $values = array_map(fn ($v) => $v === '' || $v === null ? null : (float) $v,
                $request->only(['broker_pct', 'scc_pct', 'safety_pct']) + ['broker_pct' => null, 'scc_pct' => null, 'safety_pct' => null]);
            MarketFee::updateOrCreate($key, $values);
        }

        return response()->json(['ok' => true]);
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

    /** The character whose skills set the fees: the chosen one, else one docked there, else the main character. */
    private function seller(mixed $chosen, array $docked, array $characters): ?int
    {
        if ($chosen !== null && isset($characters[(int) $chosen])) {
            return (int) $chosen;
        }
        if ($docked !== []) {
            return $docked[0];
        }
        $main = auth()->user()->main_character_id;
        if ($main && isset($characters[$main])) {
            return (int) $main;
        }

        return array_key_first($characters);
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

    private function fees(Market $market, ?int $sellerId): Fees
    {
        $skills = $sellerId === null ? collect() : DB::table('character_skills')->where('character_id', $sellerId)
            ->whereIn('skill_id', [Fees::ACCOUNTING, Fees::BROKER_RELATIONS])->pluck('active_skill_level', 'skill_id');

        return Fees::for(
            $market->isStructure(),
            (int) ($skills[Fees::ACCOUNTING] ?? 0),
            (int) ($skills[Fees::BROKER_RELATIONS] ?? 0),
            MarketFee::savedFor(auth()->user()->id, $market->locationId),
        );
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
        ];
    }
}
