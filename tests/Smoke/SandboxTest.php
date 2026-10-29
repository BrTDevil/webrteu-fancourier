<?php

declare(strict_types=1);

use WeBRTeu\Shipping\Facades\Shipping;

it('creates, prints and deletes an AWB with the test account', function (): void {
    $carrier = Shipping::carrier('fancourier');
    $awb = $carrier->createShipment(shipmentData(['reference' => 'SMOKE-'.time()]));

    expect($carrier->label($awb->awb))->toStartWith('%PDF')
        ->and($carrier->cancel($awb->awb))->toBeTrue();
})->group('sandbox')->skip(! env('FANCOURIER_SANDBOX_USERNAME'), 'Setează FANCOURIER_SANDBOX_USERNAME / _PASSWORD / _CLIENT_ID.');
