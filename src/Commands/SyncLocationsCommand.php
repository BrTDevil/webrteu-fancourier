<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier\Commands;

use Illuminate\Console\Command;
use WeBRTeu\FanCourier\FanCourierClient;
use WeBRTeu\FanCourier\Models\FanLocality;
use WeBRTeu\Shipping\Support\Normalizer;

class SyncLocationsCommand extends Command
{
    protected $signature = 'fancourier:sync-locations';

    protected $description = 'Sincronizează nomenclatorul FAN Courier (județe + localități) în fancourier_localities';

    public function handle(FanCourierClient $client): int
    {
        $counties = $client->json($client->get('/reports/counties'));
        $synced = 0;

        foreach (is_array($counties['data'] ?? null) ? $counties['data'] : [] as $county) {
            $countyName = is_array($county) && is_scalar($county['name'] ?? null) ? (string) $county['name'] : '';

            if ($countyName === '') {
                continue;
            }

            $localities = $client->json($client->get('/reports/localities', ['county' => $countyName, 'perPage' => 1000]));

            foreach (is_array($localities['data'] ?? null) ? $localities['data'] : [] as $locality) {
                if (! is_array($locality) || ! is_scalar($locality['name'] ?? null)) {
                    continue;
                }

                FanLocality::query()->updateOrCreate([
                    'county_key' => Normalizer::key($countyName),
                    'name_key' => Normalizer::key((string) $locality['name']),
                ], [
                    'fan_id' => is_numeric($locality['id'] ?? null) ? (int) $locality['id'] : null,
                    'county' => $countyName,
                    'name' => (string) $locality['name'],
                    'agency' => is_scalar($locality['agency'] ?? null) ? (string) $locality['agency'] : null,
                    'exterior_km' => is_numeric($locality['exteriorKm'] ?? null) ? (int) $locality['exteriorKm'] : 0,
                ]);

                $synced++;
            }
        }

        $this->info("Localități sincronizate: {$synced}.");

        return self::SUCCESS;
    }
}
