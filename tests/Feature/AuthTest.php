<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\FanCourier\Exceptions\FanCourierException;
use WeBRTeu\Shipping\Exceptions\AuthenticationException;
use WeBRTeu\Shipping\Facades\Shipping;

function logins(): int
{
    return collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/login'))->count();
}

it('asks for the token once and caches it', function (): void {
    fakeFan([FAN.'/reports/services*' => Http::response(fixtureFile('services.json'))]);

    Shipping::carrier('fancourier')->services();
    Shipping::carrier('fancourier')->services();

    expect(logins())->toBe(1);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/reports/services') && $request->hasHeader('Authorization', 'Bearer fan-token-1'));
});

it('re-logins once on an expired token and retries the request once', function (): void {
    fakeFan([
        FAN.'/login*' => Http::sequence()->push(fixtureFile('login.json'))->push(['status' => 'success', 'data' => ['token' => 'fan-token-2']]),
        FAN.'/reports/services*' => fn (Request $request) => $request->hasHeader('Authorization', 'Bearer fan-token-1')
            ? Http::response(fixtureFile('token-expired.json'), 401)
            : Http::response(fixtureFile('services.json')),
    ]);

    expect(Shipping::carrier('fancourier')->services())->toHaveCount(2)
        ->and(logins())->toBe(2);
});

it('does not loop when the token is rejected again', function (): void {
    fakeFan([FAN.'/reports/services*' => Http::response(fixtureFile('token-expired.json'), 401)]);

    expect(fn () => Shipping::carrier('fancourier')->services())->toThrow(AuthenticationException::class);
    expect(logins())->toBe(2);
    Http::assertSentCount(4);
});

it('reports wrong credentials', function (): void {
    Http::fake([FAN.'/login*' => Http::response(['status' => 'fail', 'message' => 'Invalid credentials'], 401)]);

    Shipping::carrier('fancourier')->services();
})->throws(AuthenticationException::class, 'FANCOURIER_USERNAME');

it('reports a login timeout', function (): void {
    Http::fake([FAN.'/login*' => fn () => throw new ConnectionException('timeout')]);

    Shipping::carrier('fancourier')->services();
})->throws(FanCourierException::class, 'autentificare');

it('reports other API errors with the message', function (mixed $body, string $message): void {
    fakeFan([FAN.'/reports/services*' => Http::response($body, 422)]);

    expect(fn () => Shipping::carrier('fancourier')->services())->toThrow(FanCourierException::class, $message);
})->with([
    [['message' => 'Parametru lipsă'], 'Parametru lipsă'],
    [['data' => ['message' => ['clientId' => 'obligatoriu']]], 'obligatoriu'],
    ['text simplu', 'eroare necunoscută'],
]);
