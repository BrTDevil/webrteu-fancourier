<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier\Support;

use WeBRTeu\FanCourier\Data\LocalityData;
use WeBRTeu\FanCourier\Exceptions\LocalityNotFoundException;
use WeBRTeu\FanCourier\Models\FanLocality;
use WeBRTeu\Shipping\Support\Normalizer;

/**
 * Găsește localitatea în nomenclatorul FAN (tabela fancourier_localities), ignorând diacriticele și prefixele.
 * Cât timp nomenclatorul nu e sincronizat, trimite numele primite, fără diacritice.
 */
final class LocalityMatcher
{
    public static function find(string $county, string $city): LocalityData
    {
        if (! FanLocality::query()->exists()) {
            return new LocalityData(Normalizer::ascii($county), Normalizer::ascii($city));
        }

        $countyKey = Normalizer::key($county);
        $cityKey = Normalizer::key($city);

        // „Sector 1”...„Sector 6”, „București Sector 3” → localitatea București.
        if (preg_match('/sector[1-6]$/', $cityKey) === 1 || in_array($countyKey, ['bucuresti', 'municipiulbucuresti'], true)) {
            $countyKey = 'bucuresti';
            $cityKey = 'bucuresti';
        }

        $locality = FanLocality::query()
            ->where('county_key', $countyKey)
            ->where('name_key', $cityKey)
            ->first();

        if (! $locality instanceof FanLocality) {
            throw LocalityNotFoundException::for($county, $city);
        }

        return new LocalityData($locality->county, $locality->name, $locality->fan_id);
    }
}
