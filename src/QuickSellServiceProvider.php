<?php

namespace Glubschi\Seat\QuickSell;

use Seat\Services\AbstractSeatPlugin;

/**
 * Registers Quick Sell with SeAT.
 *
 * Extends AbstractSeatPlugin (not Laravel's plain ServiceProvider) as the SeAT package development guide
 * requires: only then is the plugin listed under Settings > SeAT Settings > SeAT Module Versions and can it
 * register permissions.
 */
class QuickSellServiceProvider extends AbstractSeatPlugin
{
    /** Name used for views and translations: view('quicksell::…'), trans('quicksell::…'). */
    public const NS = 'quicksell';

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views', self::NS);
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', self::NS);
    }

    public function register(): void
    {
        // Sidebar entry, merged into SeAT's menu config (package.sidebar).
        $this->mergeConfigFrom(__DIR__ . '/Config/Menu/package.sidebar.php', 'package.sidebar');

        // Permissions: the keys in this file become "quicksell.<key>" (e.g. quicksell.use).
        $this->registerPermissions(__DIR__ . '/Config/Permissions/quicksell.php', self::NS);
    }

    public function getName(): string
    {
        return 'Quick Sell';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/mr-glubschi/seat-quick-sell';
    }

    public function getPackagistPackageName(): string
    {
        return 'seat-quick-sell';
    }

    public function getPackagistVendorName(): string
    {
        return 'mr-glubschi';
    }

    public function getDescription(): ?string
    {
        return 'Price items for selling at the market where you are and copy them into the Sell Items window.';
    }
}
