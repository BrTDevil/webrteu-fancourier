# webrteu/fancourier

[![PHP](https://img.shields.io/badge/PHP-%5E8.2-777bb4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12%E2%80%9313-ff2d20)](https://laravel.com/)
[![Licență MIT](https://img.shields.io/badge/licen%C8%9B%C4%83-MIT-green)](LICENSE.md)
[![Packagist](https://img.shields.io/packagist/v/webrteu/fancourier)](https://packagist.org/packages/webrteu/fancourier)

Integrare Laravel pentru FAN Courier, API v2.0 (selfAWB, JSON): AWB, etichete, tracking pe mai multe AWB-uri,
FANbox/PayPoint, comandă de curier și nomenclatorul de localități. Implementează contractele din `webrteu/shipping`.

Nu este un pachet oficial FAN Courier.

## Cerințe

- PHP `^8.2`, Laravel 12–13
- contract cu FAN Courier și cont selfAWB (`selfawb@fancourier.ro`; integrare: `presales@fancourier.ro`)
- `webrteu/shipping`

## Instalare

```bash
composer require webrteu/fancourier
php artisan vendor:publish --tag=fancourier-config
php artisan vendor:publish --tag=fancourier-migrations
php artisan migrate
php artisan fancourier:sync-locations
```

## Configurare

```dotenv
FANCOURIER_USERNAME=          # utilizator selfAWB
FANCOURIER_PASSWORD=
FANCOURIER_CLIENT_ID=         # id client din selfAWB
FANCOURIER_DEFAULT_SERVICE=Standard
FANCOURIER_FANBOX_SERVICE=FANbox   # numele exact din php artisan fancourier:services
FANCOURIER_PAYMENT=sender     # cine plătește transportul: sender | recipient
FANCOURIER_COD_PAYMENT=cash   # ramburs: cash | cont (cont colector)
FANCOURIER_TIMEOUT=15
```

FAN nu are sandbox public: testarea se face cu un cont de test primit de la FAN.

⚠️ Căile și numele câmpurilor urmează documentația v2.0 (PDF, septembrie 2025) și trebuie confirmate pe contul de test:
`/login`, `/reports/services`, `/reports/awb/internal-tariff`, `/intern-awb`, `/awb/label`, `/reports/awb/tracking`,
`DELETE /awb`, `/order`, `/reports/pickup-points`, `/reports/counties`, `/reports/localities`, plus opțiunile `A`
(deschidere colet) și `S` (sâmbătă).

## Utilizare

```php
use WeBRTeu\Shipping\Facades\Shipping;

$awb = Shipping::carrier('fancourier')->createShipment($data);   // ShipmentData din webrteu/shipping
$pdf = Shipping::carrier('fancourier')->label($awb->awb);         // A6 PDF implicit
```

| Metodă | FAN |
|---|---|
| `services()` | `/reports/services` |
| `quote($data)` | tarif intern, în bani |
| `createShipment($data)` | `/intern-awb` cu o singură linie; eroare pe linie → `ShipmentValidationException` (chiar cu HTTP 200); timeout → `ShipmentStateUnknownException`, fără retry |
| `label($awb, $format)` | `/awb/label` (PDF A4/A6 sau ZPL) |
| `track($awb)` / `trackMany($awbs)` | `/reports/awb/tracking`, un singur apel pentru mai multe AWB-uri |
| `cancel($awb)` | `DELETE /awb` |
| `pickupPoints($county, $city)` | tabela `fancourier_pickup_points` (sau API-ul, până la sincronizare) |
| `orderPickup($request)` / `FanCourier::cancelPickup($id)` | `/order` |

**Generarea AWB-ului nu înseamnă ridicarea coletului.** Fără ridicare zilnică în contract, plasează și o comandă de curier:

```php
use WeBRTeu\FanCourier\Facades\FanCourier;
use WeBRTeu\Shipping\Data\PickupRequestData;

FanCourier::orderPickup(new PickupRequestData(now()->toImmutable(), '10:00', '16:00', parcels: 3, totalWeightKg: 4.5));
```

Localitatea și județul se potrivesc automat cu nomenclatorul FAN (diacritice, prefixe, „Sector 1” → București). O
localitate inexistentă aruncă `LocalityNotFoundException`. Până la prima sincronizare se trimit numele fără diacritice.

FANbox: `pickupPointId` pe `ShipmentData` → serviciul `FANCOURIER_FANBOX_SERVICE` + `pickupLocation`.

## Comenzi Artisan

| Comandă | Ce face |
|---|---|
| `fancourier:sync-locations` | județe + localități în `fancourier_localities` |
| `fancourier:sync-pudo` | FANbox / PayPoint în `fancourier_pickup_points` |
| `fancourier:services` | serviciile contului |
| `shipping:sync` | (din core) statusurile, prin `trackMany` |

```php
Schedule::command('fancourier:sync-pudo')->daily();
Schedule::command('fancourier:sync-locations')->weekly();
Schedule::command('shipping:sync --carrier=fancourier')->hourly()->withoutOverlapping();
```

## Statusuri

⚠️ `FanCourierStatusMap` conține doar `S2` → livrat. Restul se deduce din etichete și se completează din tracking
real. Suprascriere: `config('shipping.status_map.fancourier')`.

## Ce nu face pachetul

- nu sincronizează străzile din nomenclator (doar județe și localități)
- nu suportă expeditori diferiți de contul selfAWB
- nu face retry pe crearea AWB-urilor

## Testare

```bash
composer test
FANCOURIER_SANDBOX_USERNAME=... FANCOURIER_SANDBOX_PASSWORD=... FANCOURIER_SANDBOX_CLIENT_ID=... vendor/bin/pest --group=sandbox
```

Fixture-urile sunt după documentație. **De înlocuit cu răspunsuri reale**.

## Changelog

Vezi [CHANGELOG.md](CHANGELOG.md).

## Autor

[WeBRTeu](https://webrteu.eu)

## Licență

MIT. Vezi [LICENSE.md](LICENSE.md).
