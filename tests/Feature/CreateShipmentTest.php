<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Shipping\Exceptions\ShipmentStateUnknownException;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Facades\Shipping;

it('creates an AWB from the first line of the response', function (): void {
    fakeFan([FAN.'/intern-awb' => Http::response(fixtureFile('intern-awb-ok.json'))]);

    $awb = Shipping::carrier('fancourier')->createShipment(shipmentData());

    expect($awb->awb)->toBe('2150012345678')
        ->and($awb->cost)->toBe(1892)
        ->and($awb->parcelNumbers)->toBe(['2150012345678']);

    Http::assertSent(fn (Request $request): bool => $request->url() === FAN.'/intern-awb'
        && $request['clientId'] === '7032158'
        && count($request['shipments']) === 1
        && $request['shipments'][0]['info']['cod'] === 149.7);
});

it('throws ShipmentValidationException for an error on the line despite HTTP 200', function (): void {
    fakeFan([FAN.'/intern-awb' => Http::response(fixtureFile('intern-awb-line-error.json'))]);

    expect(fn () => Shipping::carrier('fancourier')->createShipment(shipmentData()))
        ->toThrow(fn (ShipmentValidationException $e) => expect($e->errors)->toBe([
            'recipient.phone' => ['Numărul de telefon este invalid.'],
            'info.weight' => ['Greutatea este obligatorie.'],
        ]));
});

it('throws when no AWB number comes back', function (mixed $errors): void {
    fakeFan([FAN.'/intern-awb' => Http::response(['response' => [['awbNumber' => null, 'errors' => $errors]]])]);

    expect(fn () => Shipping::carrier('fancourier')->createShipment(shipmentData()))
        ->toThrow(fn (ShipmentValidationException $e) => expect(array_key_first($e->errors))->toBe('general'));
})->with(['null' => [null], 'gol' => [[]], 'listă fără câmpuri' => [[['nested' => 'x']]]]);

it('throws ShipmentStateUnknownException on timeout, without retry', function (): void {
    $attempts = 0;
    fakeFan([FAN.'/intern-awb' => function () use (&$attempts): never {
        $attempts++;

        throw new ConnectionException('timeout');
    }]);

    expect(fn () => Shipping::carrier('fancourier')->createShipment(shipmentData()))
        ->toThrow(fn (ShipmentStateUnknownException $e) => expect($e->reference)->toBe('CMD-1024'));
    expect($attempts)->toBe(1);
});

it('accepts an AWB without a tariff', function (): void {
    fakeFan([FAN.'/intern-awb' => Http::response(['response' => [['awbNumber' => '1', 'errors' => null]]])]);

    expect(Shipping::carrier('fancourier')->createShipment(shipmentData())->cost)->toBeNull();
});

it('quotes with the internal tariff', function (): void {
    fakeFan([FAN.'/reports/awb/internal-tariff*' => Http::response(fixtureFile('tariff.json'))]);

    $quote = Shipping::carrier('fancourier')->quote(shipmentData());

    expect($quote->amount)->toBe(1892)
        ->and($quote->serviceCode)->toBe('Standard');
    Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'recipient[locality]=Rasnov'));
});

it('quotes zero when the tariff is missing', function (): void {
    fakeFan([FAN.'/reports/awb/internal-tariff*' => Http::response(['data' => []])]);

    expect(Shipping::carrier('fancourier')->quote(shipmentData())->amount)->toBe(0);
});
