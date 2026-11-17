# Quick Sell for SeAT

A [SeAT 5](https://github.com/eveseat/seat) plugin for selling items quickly at the market where you are.
Select items in the EVE inventory, copy them (Ctrl+C), paste them into Quick Sell, and copy the priced list
back into the **Import Item Prices** button of EVE's *Sell Items* window.

> **Status: v0.2 (core function).**

## What it does

Quick Sell shows three ways to turn the pasted items into ISK at the chosen market, side by side, plus the best
mix (the best way of every item):

- **Sell now:** into the buy orders at this market, best price first, sales tax only.
- **Reprocess:** the materials of each item (SeAT's SDE: `invTypeMaterials`, `portionSize`), times the yield,
  minus the reprocessing tax, sold into the buy orders at this market. Yield after the EVE University wiki:
  ore and ice `base x (1 + 3% Reprocessing) x (1 + 2% Reprocessing Efficiency) x (1 + 2% ore skill)`, everything
  else `base x (1 + 2% Scrapmetal Processing)`. Whole portions only (100 units of ore).
- **Market:** a sell order one valid EVE price step (at most four significant digits) under the lowest sell
  order of other players, minus broker's fee, SCC surcharge, sales and safety tax. Your own orders are ignored.

Also:

- **Market choice:** where your characters are docked, the last market you used, the five trade hubs, or a
  search over NPC stations and the structures SeAT knows.
- **Character:** your main by default; its skills count for reprocessing.
- **Fees:** all entered by hand as the game shows them (sales tax, broker's fee, SCC, safety tax, reprocessing
  tax and base yield); the last values are kept per market.
- **Marks:** blue row = nobody else sells it there, so the market price is Jita sell; icons for your own sell
  order and for a buy order paying at least your price; the percentage against Jita sell.
- **Copy:** the items whose best way is the market (or all), one line per item, `name<TAB>price<TAB>quantity`,
  in the order of the *Sell Items* window.

### Data and storage

| Data | Source | Kept |
| --- | --- | --- |
| Item names | SeAT's SDE (`invTypes`, English) | read only |
| Stations, structures, where characters are docked, own orders, skills, reprocessing materials | SeAT's tables | read only |
| Order books (20 best orders per side and item, with volumes), for the items and their materials | ESI, fetched by SeAT jobs: `/markets/{region}/orders/` for stations and Jita, `/markets/structures/{id}/` with a character token | Laravel cache, exactly as long as ESI's `Expires` header says (5 minutes) |
| Fees and reprocessing values you last used per market | you | table `quicksell_market_fees` |
| Last used market | you | SeAT user setting |

Structure markets need a character with the `esi-markets.structure_markets.v1` scope (part of SeAT's
default scopes) and access to the structure's market.

## Install

Quick Sell is installed like any other SeAT plugin (see SeAT's
[community packages guide](https://github.com/eveseat/docs/blob/master/docs/community_packages.md)):

1. Add the package to `SEAT_PLUGINS` in your SeAT `.env`, for example
   `SEAT_PLUGINS=mr-glubschi/seat-quick-sell`
   (comma-separated if you already use other plugins).
2. Restart the SeAT stack. On start, SeAT installs the plugin and runs its migrations.
3. In SeAT, give the permission **Use Quick Sell** (`quicksell.use`) to a role, under
   *Settings → Access Management*. Administrators have it automatically.

## Roadmap

| Version | Content |
| --- | --- |
| 0.1 | Skeleton: package, menu entry, permission, empty page |
| 0.2 | Core function: paste, market choice, prices at the market and Jita, price step, marks, fees, copy |
| 0.2.1 | Rows and copied lines in the order of the Sell Items window (first pasted item at the bottom) |
| 0.3.0 | Reset button, page layout always visible, item icons (SeAT image helper), clearer legend |
| 0.4.0 | Sell now vs reprocess vs market with best mix, all fees by hand per market, main character by default |

Item names follow SeAT: English only, from the SDE. There is no price history; Jita is the anchor.

## Development

```bash
composer install
vendor/bin/phpunit
```

The tests boot the plugin with [Orchestra Testbench](https://github.com/orchestral/testbench) without
the rest of SeAT. To try the plugin in a running SeAT, follow SeAT's
[developer installation](https://github.com/eveseat/docs/blob/master/docs/developer_guides/developer_installation.md):
clone this repository into the `packages` folder of your SeAT stack and register it in
`packages/override.json`:

```json
{
  "autoload": { "Glubschi\\Seat\\QuickSell\\": "packages/mr-glubschi/seat-quick-sell/src/" },
  "providers": [ "Glubschi\\Seat\\QuickSell\\QuickSellServiceProvider" ]
}
```

## License

GNU General Public License v2.0 or later, like SeAT itself. See [LICENSE](LICENSE).
