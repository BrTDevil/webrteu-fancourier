<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use WeBRTeu\FanCourier\Commands\ServicesCommand;
use WeBRTeu\FanCourier\Commands\SyncLocationsCommand;
use WeBRTeu\FanCourier\Commands\SyncPudoCommand;
use WeBRTeu\Shipping\ShippingManager;

class FanCourierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fancourier.php', 'fancourier');

        $this->app->singleton(FanCourierClient::class);
        $this->app->singleton(FanCourierCarrier::class);

        $this->callAfterResolving(ShippingManager::class, function (ShippingManager $shipping): void {
            $shipping->extend('fancourier', fn (Container $app): FanCourierCarrier => $app->make(FanCourierCarrier::class));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/fancourier.php' => $this->app->configPath('fancourier.php'),
        ], 'fancourier-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_fancourier_tables.php.stub' => $this->app->databasePath('migrations/'.date('Y_m_d_His').'_create_fancourier_tables.php'),
        ], 'fancourier-migrations');

        $this->commands([SyncLocationsCommand::class, SyncPudoCommand::class, ServicesCommand::class]);
    }
}
