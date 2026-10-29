<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Shipping\Enums\ShipmentStatus;
use WeBRTeu\Shipping\Facades\Shipping;
use WeBRTeu\Shipping\Models\Shipment;

it('tracks several AWBs with one call, indexed by AWB', function (): void {
    fakeFan([FAN.'/reports/awb/tracking*' => Http::response(fixtureFile('tracking-multi.json'))]);

    $results = Shipping::carrier('fancourier')->trackMany(['2150012345678', '2150012345679']);

    expect($results->keys()->map(strval(...))->all())->toBe(['2150012345678', '2150012345679'])
        ->and($results['2150012345678']->status)->toBe(ShipmentStatus::Delivered)
        ->and($results['2150012345678']->events[0]->code)->toBe('C0')
        ->and($results['2150012345678']->deliveredAt?->toDateTimeString())->toBe('2026-01-03 12:10:00')
        ->and($results['2150012345679']->events[0]->occurredAt)->toBeNull()
        ->and($results['2150012345679']->events[0]->location)->toBeNull();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'awb[1]=2150012345679'));
});

it('tracks a single AWB and reports a missing one', function (): void {
    fakeFan([FAN.'/reports/awb/tracking*' => Http::response(fixtureFile('tracking-multi.json'))]);

    expect(Shipping::carrier('fancourier')->track('2150012345678')->status)->toBe(ShipmentStatus::Delivered)
        ->and(Shipping::carrier('fancourier')->track('000')->status)->toBe(ShipmentStatus::Unknown);
});

it('is used by shipping:sync', function (): void {
    fakeFan([FAN.'/reports/awb/tracking*' => Http::response(fixtureFile('tracking-multi.json'))]);
    Shipment::query()->create(['carrier' => 'fancourier', 'awb' => '2150012345678', 'status' => ShipmentStatus::Created]);

    $this->artisan('shipping:sync')->expectsOutput('Verificate: 1, schimbate: 1, erori: 0.');
});
