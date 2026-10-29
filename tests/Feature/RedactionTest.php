<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use WeBRTeu\Shipping\Facades\Shipping;

it('never logs the password or the token', function (): void {
    Log::spy();
    Http::fake([FAN.'/login*' => Http::response(['status' => 'fail', 'password' => 'secret-pass', 'token' => 'x'], 401)]);

    try {
        Shipping::carrier('fancourier')->services();
    } catch (Throwable) {
    }

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
        expect(json_encode($context))->not->toContain('secret-pass')->not->toContain('"x"');

        return true;
    });
});
