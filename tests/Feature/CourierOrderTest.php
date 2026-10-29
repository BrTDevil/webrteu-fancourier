<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\FanCourier\Facades\FanCourier;
use WeBRTeu\Shipping\Data\PickupRequestData;
use WeBRTeu\Shipping\Enums\LabelFormat;
use WeBRTeu\Shipping\Facades\Shipping;

it('orders and cancels a courier pickup', function (): void {
    fakeFan([
        FAN.'/order' => Http::response(fixtureFile('order.json')),
        FAN.'/order?*' => Http::response(['status' => 'success']),
    ]);

    $id = FanCourier::orderPickup(new PickupRequestData(CarbonImmutable::parse('2026-01-05'), '10:00', '16:00', 3, 4.5, observations: 'Poarta 2'));

    expect($id)->toBe('98765')
        ->and(FanCourier::cancelPickup($id))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === FAN.'/order' && $request['info']['date'] === '2026-01-05' && $request['info']['parcels'] === 3);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), 'id=98765'));
});

it('returns an empty id when FAN does not send one', function (): void {
    fakeFan([FAN.'/order' => Http::response(['status' => 'success'])]);

    expect(FanCourier::orderPickup(new PickupRequestData(CarbonImmutable::now(), '10:00', '12:00', 1, 1.0)))->toBe('');
});

it('downloads labels and cancels AWBs', function (LabelFormat $format, string $expected): void {
    fakeFan([
        FAN.'/awb/label*' => Http::response(fixtureFile('label.pdf')),
        FAN.'/awb?*' => Http::response(['status' => 'success']),
    ]);

    expect(Shipping::carrier('fancourier')->label('2150012345678', $format))->toStartWith('%PDF')
        ->and(Shipping::carrier('fancourier')->cancel('2150012345678'))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/awb/label') && str_contains($request->url(), $expected));
})->with([
    [LabelFormat::A6Pdf, 'format=A6'],
    [LabelFormat::A4Pdf, 'format=A4'],
    [LabelFormat::Zpl, 'zpl=1'],
]);

it('lists services and marks FANbox', function (): void {
    fakeFan([FAN.'/reports/services*' => Http::response(fixtureFile('services.json'))]);

    $services = Shipping::carrier('fancourier')->services();

    expect($services->pluck('code')->all())->toBe(['Standard', 'FANbox'])
        ->and($services[0]->name)->toBe('Serviciul standard')
        ->and($services[1]->name)->toBe('FANbox')
        ->and($services[1]->pickupPoint)->toBeTrue();

    $this->artisan('fancourier:services')->expectsTable(['Cod', 'Descriere', 'FANbox'], [
        ['Standard', 'Serviciul standard', 'nu'],
        ['FANbox', 'FANbox', 'da'],
    ]);
});
