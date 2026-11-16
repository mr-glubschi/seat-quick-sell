# Quick Sell for SeAT

A [SeAT 5](https://github.com/eveseat/seat) plugin for selling items quickly at the market where you are.
Paste items copied from EVE, pick the market (trade hub, NPC station or a structure you can see), choose a
price rule, and copy the result into the **Import Item Prices** button of EVE's *Sell Items* window.

> **Status: v0.1 (skeleton).** The plugin installs, adds the *Quick Sell* menu entry and the
> `quicksell.use` permission, and shows an empty page. Pricing arrives with the next versions;
> see the roadmap below.

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
| 0.2 | Market data: structure and station order books fetched by SeAT jobs, saved markets with fees |
| 0.3 | Pricing: paste items (English or German names), price rules, valid EVE price steps, fees, export |
| 1.0 | Region sales history, markets where your characters are docked, German interface |

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
