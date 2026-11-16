<?php

namespace Glubschi\Seat\QuickSell\Tests;

use Glubschi\Seat\QuickSell\QuickSellServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Boots a bare Laravel app with only the Quick Sell provider. SeAT's own providers are not loaded:
 * they need MariaDB, Redis and the SDE, and these tests only check what the plugin registers.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [QuickSellServiceProvider::class];
    }

    /** SQLite in memory instead of MariaDB, and the array cache instead of Redis. */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
    }
}
