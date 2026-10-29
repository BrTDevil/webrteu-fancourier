<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use WeBRTeu\Shipping\Data\AddressData;
use WeBRTeu\Shipping\Data\ParcelData;
use WeBRTeu\Shipping\Data\ShipmentData;

const FAN = 'https://api.fancourier.ro';

function fixtureFile(string $name): string
{
    return (string) file_get_contents(__DIR__.'/Fixtures/'.$name);
}

/**
 * @param  array<string, mixed>  $routes
 */
function fakeFan(array $routes = []): void
{
    Http::fake($routes + [FAN.'/login*' => Http::response(fixtureFile('login.json'))]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function shipmentData(array $overrides = []): ShipmentData
{
    return new ShipmentData(...$overrides + [
        'recipient' => new AddressData(
            name: 'Ion Popescu',
            phone: '+40 721 000 000',
            county: 'Brașov',
            city: 'Râșnov',
            street: 'Str. Republicii',
            number: '10',
            email: 'ion@example.com',
            block: 'A',
            staircase: '1',
            floor: '2',
            apartment: '3',
            postalCode: '505400',
        ),
        'parcels' => [new ParcelData(1.2, 30, 20, 10)],
        'reference' => 'CMD-1024',
        'codAmount' => 14970,
        'declaredValue' => 20000,
    ]);
}
