<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use WeBRTeu\FanCourier\FanCourierStatusMap;
use WeBRTeu\Shipping\Enums\ShipmentStatus;

it('maps FAN codes and labels', function (string $code, string $label, ShipmentStatus $expected): void {
    expect(FanCourierStatusMap::resolve($code, $label))->toBe($expected);
})->with([
    ['S2', 'Orice', ShipmentStatus::Delivered],
    ['C0', 'Expediere ridicată', ShipmentStatus::PickedUp],
    ['H4', 'Expediere în tranzit', ShipmentStatus::InTransit],
    ['S1', 'Expediere în livrare', ShipmentStatus::OutForDelivery],
]);

it('logs unknown codes', function (): void {
    Log::spy();

    expect(FanCourierStatusMap::resolve('Z9', 'Necunoscut'))->toBe(ShipmentStatus::Unknown);
    Log::shouldHaveReceived('warning')->once();
});
