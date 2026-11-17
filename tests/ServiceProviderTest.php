<?php

namespace Glubschi\Seat\QuickSell\Tests;

use Glubschi\Seat\QuickSell\QuickSellServiceProvider;
use Illuminate\Support\Facades\Route;

class ServiceProviderTest extends TestCase
{
    public function test_registers_the_page_route_with_seat_middleware(): void
    {
        $route = Route::getRoutes()->getByName('seatquicksell::home');

        $this->assertNotNull($route);
        $this->assertSame('quick-sell', $route->uri());
        $this->assertSame(['web', 'auth', 'locale', 'can:quicksell.use'], $route->gatherMiddleware());
    }

    public function test_all_endpoints_use_the_same_middleware(): void
    {
        foreach (['markets' => 'quick-sell/markets', 'quote' => 'quick-sell/quote', 'status' => 'quick-sell/status', 'fees' => 'quick-sell/fees', 'fees.reset' => 'quick-sell/fees/reset'] as $name => $uri) {
            $route = app('router')->getRoutes()->getByName('seatquicksell::' . $name);
            $this->assertNotNull($route, $name);
            $this->assertSame($uri, $route->uri());
            $this->assertSame(['web', 'auth', 'locale', 'can:quicksell.use'], $route->gatherMiddleware());
        }
    }

    public function test_registers_the_use_permission(): void
    {
        $permission = config('seat.permissions.quicksell.use');

        $this->assertIsArray($permission);
        $this->assertSame('quicksell::permissions.use_label', $permission['label']);
    }

    public function test_adds_a_sidebar_entry_guarded_by_the_permission(): void
    {
        $entry = config('package.sidebar.quicksell');

        $this->assertSame('seatquicksell::home', $entry['route']);
        $this->assertSame('quicksell.use', $entry['permission']);
        $this->assertSame('quick-sell', $entry['route_segment']);
    }

    public function test_reports_name_and_packagist_coordinates(): void
    {
        $provider = new QuickSellServiceProvider($this->app);

        $this->assertSame('Quick Sell', $provider->getName());
        $this->assertSame('mr-glubschi', $provider->getPackagistVendorName());
        $this->assertSame('seat-quick-sell', $provider->getPackagistPackageName());
    }

    public function test_translation_keys_resolve(): void
    {
        $this->assertSame('Quick Sell', trans('quicksell::menu.main'));
        $this->assertSame('Use Quick Sell', trans('quicksell::permissions.use_label'));
    }
}
