<?php

declare(strict_types=1);

use WeBRTeu\FanCourier\Data\LocalityData;
use WeBRTeu\FanCourier\Requests\InternAwbPayload;
use WeBRTeu\Shipping\Data\AddressData;
use WeBRTeu\Shipping\Data\ParcelData;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;

function payload(array $overrides = []): array
{
    return (new InternAwbPayload('Standard', 'FANbox', 'sender', 'cash'))->make(shipmentData($overrides), new LocalityData('Brasov', 'Rasnov'));
}

it('sends cash on delivery and declared value in lei', function (): void {
    $info = payload()['info'];

    expect($info['cod'])->toBe(149.7)
        ->and($info['declaredValue'])->toBe(200.0)
        ->and($info['refund'])->toBe('cash')
        ->and($info['payment'])->toBe('sender')
        ->and($info['service'])->toBe('Standard')
        ->and($info['weight'])->toBe(1.2)
        ->and($info['packages'])->toBe(['parcel' => 1, 'envelope' => 0])
        ->and($info['dimensions'])->toBe(['length' => 30, 'width' => 20, 'height' => 10])
        ->and($info['costCenter'])->toBe('CMD-1024')
        ->and($info['options'])->toBe([]);
});

it('normalizes the phone and uses the FAN locality names', function (): void {
    $recipient = payload()['recipient'];

    expect($recipient['phone'])->toBe('0721000000')
        ->and($recipient['address'])->toMatchArray(['county' => 'Brasov', 'locality' => 'Rasnov', 'street' => 'Str. Republicii', 'streetNo' => '10', 'building' => 'A', 'apartment' => '3'])
        ->and($recipient)->not->toHaveKey('pickupLocation');
});

it('delivers to FANbox with the dedicated service and the PUDO id', function (): void {
    $payload = payload(['pickupPointId' => 'FAN0001']);

    expect($payload['info']['service'])->toBe('FANbox')
        ->and($payload['recipient']['pickupLocation'])->toBe('FAN0001');
});

it('adds observations, open package and saturday options', function (): void {
    $info = payload(['observations' => 'Sunați', 'openPackage' => true, 'saturdayDelivery' => true, 'serviceCode' => 'Cont Colector', 'codAmount' => 0])['info'];

    expect($info['observation'])->toBe('Sunați')
        ->and($info['options'])->toBe(['A', 'S'])
        ->and($info['service'])->toBe('Cont Colector')
        ->and($info['refund'])->toBeNull();
});

it('picks the biggest parcel for the dimensions and handles missing ones', function (): void {
    expect(payload(['parcels' => [new ParcelData(1, 10, 10, 10), new ParcelData(1, 50, 40, 30), new ParcelData(1)]])['info']['dimensions'])->toBe(['length' => 50, 'width' => 40, 'height' => 30])
        ->and(payload(['parcels' => [new ParcelData(0.05)]])['info'])->toMatchArray(['dimensions' => null, 'weight' => 0.1]);
});

it('rejects a custom sender', function (): void {
    payload(['sender' => new AddressData('Firma', '0700000000', 'Cluj', 'Cluj', 'Str. Y', '2')]);
})->throws(UnsupportedOperationException::class);
