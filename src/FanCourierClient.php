<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use WeBRTeu\FanCourier\Exceptions\FanCourierException;
use WeBRTeu\Shipping\Exceptions\AuthenticationException;
use WeBRTeu\Shipping\Support\CachedToken;
use WeBRTeu\Shipping\Support\Redactor;

/**
 * HTTP brut spre FAN Courier API v2: login, token Bearer în cache (24h oficial, 20h la noi),
 * la 401 un singur re-login și o singură reîncercare. Fără alt retry.
 */
class FanCourierClient
{
    public function tokenKey(): string
    {
        return 'fancourier:token:'.Config::string('fancourier.username', '');
    }

    public function clientId(): string
    {
        return (string) Config::get('fancourier.client_id', '');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->send('GET', $path, ['query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function post(string $path, array $body): Response
    {
        return $this->send('POST', $path, ['json' => $body]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function delete(string $path, array $query = []): Response
    {
        return $this->send('DELETE', $path, ['query' => $query]);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function json(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function redact(array $payload): array
    {
        return Redactor::redact($payload);
    }

    /**
     * @param  array{query?: array<string, mixed>, json?: array<string, mixed>}  $options
     */
    private function send(string $method, string $path, array $options, bool $retried = false): Response
    {
        $response = $this->request()
            ->withToken($this->token())
            ->send($method, $this->url($path), $options);

        if ($response->status() === 401 && ! $retried) {
            CachedToken::forget($this->tokenKey());

            return $this->send($method, $path, $options, true);
        }

        if ($response->failed()) {
            Log::warning('fancourier.request_failed', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'request' => $this->redact($options),
                'response' => $this->redact($this->json($response)),
            ]);

            if ($response->status() === 401) {
                throw new AuthenticationException('FAN Courier a respins token-ul și după re-autentificare.');
            }

            throw new FanCourierException(sprintf('FAN Courier %s %s a eșuat (HTTP %d): %s', $method, $path, $response->status(), self::message($this->json($response))));
        }

        return $response;
    }

    private function token(): string
    {
        return CachedToken::remember($this->tokenKey(), Config::integer('fancourier.token_cache_ttl', 72000), function (): string {
            try {
                $response = $this->request()->post($this->url('/login'), [
                    'username' => Config::string('fancourier.username', ''),
                    'password' => Config::string('fancourier.password', ''),
                ]);
            } catch (ConnectionException $exception) {
                throw new FanCourierException('FAN Courier nu a răspuns la autentificare: '.$exception->getMessage(), 0, $exception);
            }

            $token = data_get($this->json($response), 'data.token');

            if ($response->failed() || ! is_string($token) || $token === '') {
                Log::warning('fancourier.login_failed', ['status' => $response->status(), 'response' => $this->redact($this->json($response))]);

                throw new AuthenticationException('Autentificarea FAN Courier a eșuat: verifică FANCOURIER_USERNAME / FANCOURIER_PASSWORD.');
            }

            return $token;
        });
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()->timeout(Config::integer('fancourier.timeout', 15));
    }

    private function url(string $path): string
    {
        return rtrim(Config::string('fancourier.base_url', 'https://api.fancourier.ro'), '/').$path;
    }

    /**
     * @param  array<array-key, mixed>  $json
     */
    public static function message(array $json): string
    {
        $message = $json['message'] ?? data_get($json, 'data.message') ?? $json['error'] ?? null;

        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }

        return is_scalar($message) ? (string) $message : 'eroare necunoscută';
    }
}
