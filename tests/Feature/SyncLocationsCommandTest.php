<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use WeBRTeu\FanCourier\Facades\FanCourier;
use WeBRTeu\FanCourier\FanCourierClient;
use WeBRTeu\FanCourier\FanCourierServiceProvider;
use WeBRTeu\FanCourier\Models\FanLocality;
use WeBRTeu\FanCourier\Models\FanPickupPoint;
use WeBRTeu\Shipping\Enums\PickupPointType;
use WeBRTeu\Shipping\Facades\Shipping;

it('syncs counties and localities without duplicates', function (): void {
    fakeFan([
        FAN.'/reports/counties*' => Http::response(fixtureFile('counties.json')),
        FAN.'/reports/localities*' => Http::response(fixtureFile('localities.json')),
    ]);

    $this->artisan('fancourier:sync-locations')->expectsOutput('Localități sincronizate: 2.')->assertSuccessful();
    $this->artisan('fancourier:sync-locations');

    expect(FanLocality::query()->count())->toBe(2)
        ->and(FanLocality::query()->where('name_key', 'rasnov')->sole())
        ->fan_id->toBe(102)
        ->exterior_km->toBe(12)
        ->getTable()->toBe('fancourier_localities');
});

it('syncs FANbox and PayPoint points and deactivates missing ones', function (): void {
    FanPickupPoint::query()->create(['point_id' => 'OLD', 'name' => 'x', 'county' => 'x', 'city' => 'x', 'address' => 'x']);
    fakeFan([FAN.'/reports/pickup-points*' => Http::response(fixtureFile('pickup-points.json'))]);

    $this->artisan('fancourier:sync-pudo')->expectsOutput('Puncte sincronizate: 2, dezactivate: 1.');

    $points = Shipping::carrier('fancourier')->pickupPoints('Brașov', 'Râșnov');

    expect($points)->toHaveCount(1)
        ->and($points[0]->type)->toBe(PickupPointType::Pudo)
        ->and($points[0]->supportsCod)->toBeFalse()
        ->and(FanPickupPoint::query()->where('point_id', 'FAN0001')->sole()->getTable())->toBe('fancourier_pickup_points');
});

it('reads pickup points from the API until they are synced', function (): void {
    fakeFan([FAN.'/reports/pickup-points*' => Http::response(fixtureFile('pickup-points.json'))]);

    $points = Shipping::carrier('fancourier')->pickupPoints();

    expect($points)->toHaveCount(2)
        ->and($points[0]->type)->toBe(PickupPointType::Locker)
        ->and($points[0]->lat)->toBe(45.65)
        ->and($points[0]->address)->toBe('Str. Lunga 1')
        ->and($points[1]->postalCode)->toBeNull();
});

it('does not deactivate anything when the API returns no points', function (): void {
    fakeFan([FAN.'/reports/pickup-points*' => Http::response(['data' => []])]);

    $this->artisan('fancourier:sync-pudo')->expectsOutput('Puncte sincronizate: 0, dezactivate: 0.');
});

it('registers the carrier and publishes its files', function (): void {
    expect(Shipping::available())->toContain('fancourier')
        ->and(FanCourier::client())->toBeInstanceOf(FanCourierClient::class)
        ->and(ServiceProvider::pathsToPublish(FanCourierServiceProvider::class, 'fancourier-migrations'))->toHaveCount(1);
});
