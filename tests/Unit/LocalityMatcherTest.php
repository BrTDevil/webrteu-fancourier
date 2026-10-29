<?php

declare(strict_types=1);

use WeBRTeu\FanCourier\Exceptions\LocalityNotFoundException;
use WeBRTeu\FanCourier\Models\FanLocality;
use WeBRTeu\FanCourier\Support\LocalityMatcher;
use WeBRTeu\Shipping\Support\Normalizer;

function seedLocalities(): void
{
    foreach ([['Brasov', 'Rasnov', 102], ['Bucuresti', 'Bucuresti', 1], ['Sibiu', 'Șercaia', 5]] as [$county, $name, $id]) {
        FanLocality::query()->create([
            'fan_id' => $id,
            'county' => $county,
            'name' => $name,
            'county_key' => Normalizer::key($county),
            'name_key' => Normalizer::key($name),
        ]);
    }
}

it('matches ignoring diacritics and prefixes', function (string $county, string $city, string $expected): void {
    seedLocalities();

    expect(LocalityMatcher::find($county, $city)->locality)->toBe($expected);
})->with([
    ['Județul Brașov', 'Râșnov', 'Rasnov'],
    ['BRASOV', 'rasnov', 'Rasnov'],
    ['Sibiu', 'Şercaia', 'Șercaia'],
    ['București', 'Sector 1', 'Bucuresti'],
    ['Ilfov', 'Bucuresti Sector 3', 'Bucuresti'],
]);

it('throws for a locality that does not exist', function (): void {
    seedLocalities();

    LocalityMatcher::find('Brașov', 'Atlantida');
})->throws(LocalityNotFoundException::class, 'fancourier:sync-locations');

it('passes the names through until the nomenclature is synced', function (): void {
    $locality = LocalityMatcher::find('Brașov', 'Râșnov');

    expect($locality->county)->toBe('Brasov')
        ->and($locality->locality)->toBe('Rasnov')
        ->and($locality->id)->toBeNull();
});
