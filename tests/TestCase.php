<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier\Tests;

use Illuminate\Database\Migrations\Migration;
use Orchestra\Testbench\TestCase as Orchestra;
use WeBRTeu\FanCourier\FanCourierServiceProvider;
use WeBRTeu\Shipping\ShippingServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ShippingServiceProvider::class, FanCourierServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('fancourier.username', env('FANCOURIER_SANDBOX_USERNAME', 'test-user'));
        $app['config']->set('fancourier.password', env('FANCOURIER_SANDBOX_PASSWORD', 'secret-pass'));
        $app['config']->set('fancourier.client_id', env('FANCOURIER_SANDBOX_CLIENT_ID', '7032158'));
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach ([
            __DIR__.'/../vendor/webrteu/shipping/database/migrations/create_shipments_tables.php.stub',
            __DIR__.'/../database/migrations/create_fancourier_tables.php.stub',
        ] as $stub) {
            /** @var Migration $migration */
            $migration = require $stub;
            $migration->up();
        }
    }
}
