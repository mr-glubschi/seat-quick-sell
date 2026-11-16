# Quick Sell for SeAT

A [SeAT 5](https://github.com/eveseat/seat) plugin for selling items quickly at the market where you are.
Select items in the EVE inventory, copy them (Ctrl+C), paste them into Quick Sell, and copy the priced list
back into the **Import Item Prices** button of EVE's *Sell Items* window.

> **Status: v0.2 (core function).**

## What it does

- **Market:** where your characters are docked, the last market you used, the five trade hubs, or a search
  over NPC stations and the structures SeAT knows.
- **Prices:** for every item the best sell and buy order at that market and in Jita 4-4. Jita sell is the
  anchor price.
- **Your price:** the lowest sell order of other players, one valid EVE price step lower (at most four
  significant digits, as the client allows). Your own orders are ignored.
- **Marks:** blue row = nobody else sells it there, so the price is Jita sell; an icon when you already have
  a sell order there; an icon when a buy order pays at least your price; the percentage against Jita sell in
  green (above) or red (below).
- **Fees:** broker's fee, SCC surcharge, sales tax and safety tax with the totals, so you can check them
  against the *Sell Items* window. Sales tax and the NPC broker's fee come from the seller's Accounting and
  Broker Relations skills; standings and structure broker's fees are not in ESI, so you can correct and save
  them per market.
- **Copy:** one line per item, `name<TAB>price<TAB>quantity`.

### Data and storage

| Data | Source | Kept |
| --- | --- | --- |
| Item names | SeAT's SDE (`invTypes`, English) | read only |
| Stations, structures, where characters are docked, own orders, skills | SeAT's tables | read only |
| Order books (10 best orders per side and item) | ESI, fetched by SeAT jobs: `/markets/{region}/orders/` for stations and Jita, `/markets/structures/{id}/` with a character token | Laravel cache, exactly as long as ESI's `Expires` header says (5 minutes) |
| Fees you corrected per market | you | table `quicksell_market_fees` |
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
