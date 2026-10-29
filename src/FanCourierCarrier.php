<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use WeBRTeu\FanCourier\Models\FanPickupPoint;
use WeBRTeu\FanCourier\Requests\InternAwbPayload;
use WeBRTeu\FanCourier\Support\LocalityMatcher;
use WeBRTeu\Shipping\Contracts\Carrier;
use WeBRTeu\Shipping\Contracts\HasBulkTracking;
use WeBRTeu\Shipping\Contracts\HasCourierOrder;
use WeBRTeu\Shipping\Contracts\HasPickupPoints;
use WeBRTeu\Shipping\Data\AwbData;
use WeBRTeu\Shipping\Data\PickupPointData;
use WeBRTeu\Shipping\Data\PickupRequestData;
use WeBRTeu\Shipping\Data\QuoteData;
use WeBRTeu\Shipping\Data\ServiceData;
use WeBRTeu\Shipping\Data\ShipmentData;
use WeBRTeu\Shipping\Data\TrackingData;
use WeBRTeu\Shipping\Data\TrackingEventData;
use WeBRTeu\Shipping\Enums\LabelFormat;
use WeBRTeu\Shipping\Enums\PickupPointType;
use WeBRTeu\Shipping\Enums\ShipmentStatus;
use WeBRTeu\Shipping\Exceptions\ShipmentStateUnknownException;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Support\Money;
use WeBRTeu\Shipping\Support\Normalizer;

class FanCourierCarrier implements Carrier, HasBulkTracking, HasCourierOrder, HasPickupPoints
{
    public function __construct(private readonly FanCourierClient $client) {}

    public function name(): string
    {
        return 'fancourier';
    }

    public function client(): FanCourierClient
    {
        return $this->client;
    }

    public function services(): Collection
    {
        return collect($this->rows($this->client->json($this->client->get('/reports/services'))))
            ->map(fn (array $service): ServiceData => new ServiceData(
                code: self::string($service, 'name'),
                name: self::string($service, 'description') !== '' ? self::string($service, 'description') : self::string($service, 'name'),
                pickupPoint: str_contains(strtolower(self::string($service, 'name')), 'fanbox'),
                raw: $service,
            ))->values();
    }

    public function quote(ShipmentData $shipment): QuoteData
    {
        $payload = $this->payload();
        $locality = LocalityMatcher::find($shipment->recipient->county, $shipment->recipient->city);

        $json = $this->client->json($this->client->get('/reports/awb/internal-tariff', [
            'clientId' => $this->client->clientId(),
            'info' => [
                'service' => $payload->service($shipment),
                'payment' => Config::string('fancourier.payment', 'sender'),
                'weight' => max($shipment->totalWeightKg(), 0.1),
                'packages' => ['parcel' => $shipment->parcelCount(), 'envelope' => $shipment->envelopes],
                'declaredValue' => Money::toFloat($shipment->declaredValue),
                'cod' => Money::toFloat($shipment->codAmount),
            ],
            'recipient' => ['county' => $locality->county, 'locality' => $locality->locality],
        ]));

        return new QuoteData(
            carrier: $this->name(),
            amount: Money::fromDecimal($this->decimal(data_get($json, 'data.total'))),
            serviceCode: $payload->service($shipment),
            raw: $json,
        );
    }

    /**
     * /intern-awb primește o listă și întoarce rezultat per linie: HTTP 200 nu înseamnă AWB creat.
     */
    public function createShipment(ShipmentData $shipment): AwbData
    {
        $body = [
            'clientId' => $this->client->clientId(),
            'shipments' => [$this->payload()->make($shipment, LocalityMatcher::find($shipment->recipient->county, $shipment->recipient->city))],
        ];

        try {
            $json = $this->client->json($this->client->post('/intern-awb', $body));
        } catch (ConnectionException $exception) {
            throw new ShipmentStateUnknownException($this->name(), $shipment->reference, $exception);
        }

        $line = data_get($json, 'response.0');
        $line = is_array($line) ? $line : [];
        $errors = $line['errors'] ?? null;
        $awb = self::string($line, 'awbNumber');

        if ((is_array($errors) && $errors !== []) || $awb === '') {
            throw ShipmentValidationException::withErrors($this->name(), $this->lineErrors($errors));
        }

        return new AwbData(
            awb: $awb,
            carrier: $this->name(),
            cost: data_get($line, 'tariff.total') !== null ? Money::fromDecimal($this->decimal(data_get($line, 'tariff.total'))) : null,
            parcelNumbers: [$awb],
            raw: $line,
        );
    }

    public function label(string $awb, LabelFormat $format = LabelFormat::A6Pdf): string
    {
        return $this->client->get('/awb/label', [
            'clientId' => $this->client->clientId(),
            'awbs' => [$awb],
            'pdf' => $format === LabelFormat::Zpl ? 0 : 1,
            'zpl' => $format === LabelFormat::Zpl ? 1 : 0,
            'format' => $format === LabelFormat::A4Pdf ? 'A4' : 'A6',
        ])->body();
    }

    public function track(string $awb): TrackingData
    {
        return $this->trackMany([$awb])->get($awb) ?? new TrackingData($awb, ShipmentStatus::Unknown, 'AWB negăsit la FAN Courier');
    }

    public function trackMany(array $awbs): Collection
    {
        $json = $this->client->json($this->client->get('/reports/awb/tracking', [
            'clientId' => $this->client->clientId(),
            'awb' => $awbs,
        ]));

        /** @var Collection<string, TrackingData> $results */
        $results = collect();

        foreach ($this->rows($json) as $row) {
            $awb = self::string(is_array($row['info'] ?? null) ? $row['info'] : [], 'awbNumber');

            if ($awb === '') {
                continue;
            }

            $events = array_map($this->event(...), $this->list($row['events'] ?? null));
            $results->put($awb, TrackingData::fromEvents($awb, $events, $row));
        }

        return $results;
    }

    public function cancel(string $awb): bool
    {
        $this->client->delete('/awb', ['clientId' => $this->client->clientId(), 'awb' => $awb]);

        return true;
    }

    /**
     * Din tabela sincronizată cu fancourier:sync-pudo; dacă e goală, direct din API.
     */
    public function pickupPoints(?string $county = null, ?string $city = null): Collection
    {
        $points = FanPickupPoint::query()->where('active', true)->exists()
            ? FanPickupPoint::query()->where('active', true)->get()->map(fn (FanPickupPoint $point): PickupPointData => $this->fromModel($point))
            : $this->remotePickupPoints();

        return $points
            ->filter(fn (PickupPointData $point): bool => ($county === null || Normalizer::same($point->county, $county))
                && ($city === null || Normalizer::same($point->city, $city)))
            ->values();
    }

    /**
     * @return Collection<int, PickupPointData>
     */
    public function remotePickupPoints(): Collection
    {
        return collect($this->rows($this->client->json($this->client->get('/reports/pickup-points', ['type' => 'fanbox']))))
            ->map(fn (array $point): PickupPointData => self::pickupPoint($point))
            ->values();
    }

    /**
     * Generarea AWB-ului nu înseamnă ridicarea coletului: fără ridicare zilnică în contract, trebuie comandă de curier.
     */
    public function orderPickup(PickupRequestData $request): string
    {
        $json = $this->client->json($this->client->post('/order', [
            'clientId' => $this->client->clientId(),
            'info' => [
                'date' => $request->date->toDateString(),
                'startHour' => $request->fromHour,
                'endHour' => $request->toHour,
                'parcels' => $request->parcels,
                'envelopes' => 0,
                'weight' => $request->totalWeightKg,
                'observation' => (string) $request->observations,
            ],
        ]));

        $id = data_get($json, 'data.id');

        return is_scalar($id) ? (string) $id : '';
    }

    public function cancelPickup(string $orderId): bool
    {
        $this->client->delete('/order', ['clientId' => $this->client->clientId(), 'id' => $orderId]);

        return true;
    }

    private function payload(): InternAwbPayload
    {
        return new InternAwbPayload(
            Config::string('fancourier.default_service', 'Standard'),
            Config::string('fancourier.fanbox_service', 'FANbox'),
            Config::string('fancourier.payment', 'sender'),
            Config::string('fancourier.cod_payment', 'cash'),
        );
    }

    /**
     * @param  array<array-key, mixed>  $point
     */
    public static function pickupPoint(array $point): PickupPointData
    {
        $address = is_array($point['address'] ?? null) ? $point['address'] : [];

        return new PickupPointData(
            id: self::string($point, 'id'),
            carrier: 'fancourier',
            type: str_contains(strtolower(self::string($point, 'type')), 'paypoint') ? PickupPointType::Pudo : PickupPointType::Locker,
            name: self::string($point, 'name'),
            county: self::string($address, 'county'),
            city: self::string($address, 'locality'),
            address: trim(self::string($address, 'street').' '.self::string($address, 'streetNo')),
            lat: is_numeric($point['latitude'] ?? null) ? (float) $point['latitude'] : null,
            lng: is_numeric($point['longitude'] ?? null) ? (float) $point['longitude'] : null,
            postalCode: self::string($address, 'zipCode') !== '' ? self::string($address, 'zipCode') : null,
            supportsCod: (bool) ($point['cod'] ?? true),
            schedule: is_array($point['schedule'] ?? null) ? $point['schedule'] : null,
            raw: $point,
        );
    }

    private function fromModel(FanPickupPoint $point): PickupPointData
    {
        return new PickupPointData(
            id: $point->point_id,
            carrier: 'fancourier',
            type: $point->type === 'paypoint' ? PickupPointType::Pudo : PickupPointType::Locker,
            name: $point->name,
            county: $point->county,
            city: $point->city,
            address: $point->address,
            lat: $point->lat,
            lng: $point->lng,
            postalCode: $point->postal_code,
            supportsCod: $point->supports_cod,
            schedule: $point->schedule,
        );
    }

    /**
     * @param  array<array-key, mixed>  $event
     */
    private function event(array $event): TrackingEventData
    {
        $code = self::string($event, 'id');
        $label = self::string($event, 'name');
        $date = self::string($event, 'date');

        return new TrackingEventData(
            status: FanCourierStatusMap::resolve($code, $label),
            code: $code,
            label: $label,
            occurredAt: $date !== '' ? CarbonImmutable::parse($date, 'Europe/Bucharest') : null,
            location: self::string($event, 'location') !== '' ? self::string($event, 'location') : null,
            raw: $event,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function lineErrors(mixed $errors): array
    {
        if (! is_array($errors) || $errors === []) {
            return ['general' => ['FAN Courier nu a întors un număr de AWB.']];
        }

        $result = [];

        foreach ($errors as $field => $messages) {
            $result[is_string($field) ? $field : 'general'] = array_values(array_map(
                fn (mixed $message): string => is_scalar($message) ? (string) $message : (string) json_encode($message, JSON_UNESCAPED_UNICODE),
                is_array($messages) ? $messages : [$messages],
            ));
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $json
     * @return list<array<array-key, mixed>>
     */
    private function rows(array $json): array
    {
        return $this->list($json['data'] ?? null);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function list(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function string(array $data, string $key): string
    {
        return is_scalar($data[$key] ?? null) ? (string) $data[$key] : '';
    }

    private function decimal(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : '0';
    }
}
