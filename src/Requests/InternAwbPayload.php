<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier\Requests;

use WeBRTeu\FanCourier\Data\LocalityData;
use WeBRTeu\Shipping\Data\AddressData;
use WeBRTeu\Shipping\Data\ParcelData;
use WeBRTeu\Shipping\Data\ShipmentData;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;
use WeBRTeu\Shipping\Support\Money;
use WeBRTeu\Shipping\Support\Normalizer;

/**
 * ShipmentData → o expediție din lista trimisă la POST /intern-awb.
 * ⚠️ Numele câmpurilor urmează documentația FAN v2.0 și se confirmă cu PDF-ul oficial.
 */
final readonly class InternAwbPayload
{
    /**
     * Opțiunea FAN pentru deschiderea coletului la livrare.
     */
    public const OPTION_OPEN_PACKAGE = 'A';

    /**
     * Opțiunea FAN pentru livrare sâmbăta.
     */
    public const OPTION_SATURDAY = 'S';

    public function __construct(
        private string $defaultService,
        private string $fanboxService,
        private string $payment,
        private string $codPayment,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function make(ShipmentData $shipment, LocalityData $locality): array
    {
        if ($shipment->sender instanceof AddressData) {
            throw UnsupportedOperationException::for('fancourier', 'sender (expeditorul e contul selfAWB)');
        }

        $options = array_values(array_filter([
            $shipment->openPackage ? self::OPTION_OPEN_PACKAGE : null,
            $shipment->saturdayDelivery ? self::OPTION_SATURDAY : null,
        ]));

        $recipient = $shipment->recipient;
        $payload = [
            'info' => [
                'service' => $this->service($shipment),
                'packages' => ['parcel' => $shipment->parcelCount(), 'envelope' => $shipment->envelopes],
                'weight' => max($shipment->totalWeightKg(), 0.1),
                'cod' => Money::toFloat($shipment->codAmount),
                'declaredValue' => Money::toFloat($shipment->declaredValue),
                'payment' => $this->payment,
                'refund' => $shipment->hasCashOnDelivery() ? $this->codPayment : null,
                'returnPayment' => null,
                'observation' => (string) $shipment->observations,
                'content' => $shipment->contents,
                'dimensions' => $this->dimensions($shipment),
                'costCenter' => $shipment->reference,
                'options' => $options,
            ],
            'recipient' => [
                'name' => $recipient->name,
                'contactPerson' => $recipient->contactPerson ?? $recipient->name,
                'phone' => Normalizer::phone($recipient->phone),
                'email' => (string) $recipient->email,
                'address' => [
                    'county' => $locality->county,
                    'locality' => $locality->locality,
                    'street' => $recipient->street,
                    'streetNo' => $recipient->number,
                    'zipCode' => (string) $recipient->postalCode,
                    'building' => (string) $recipient->block,
                    'entrance' => (string) $recipient->staircase,
                    'floor' => (string) $recipient->floor,
                    'apartment' => (string) $recipient->apartment,
                ],
            ],
        ];

        if ($shipment->isPickupPointDelivery()) {
            $payload['recipient']['pickupLocation'] = $shipment->pickupPointId;
        }

        return $payload;
    }

    public function service(ShipmentData $shipment): string
    {
        if ($shipment->serviceCode !== null && $shipment->serviceCode !== '') {
            return $shipment->serviceCode;
        }

        return $shipment->isPickupPointDelivery() ? $this->fanboxService : $this->defaultService;
    }

    /**
     * Dimensiunile celui mai mare colet, în cm (FAN cere o singură set de dimensiuni pe AWB).
     *
     * @return array{length: int, width: int, height: int}|null
     */
    private function dimensions(ShipmentData $shipment): ?array
    {
        $sized = array_filter($shipment->parcels, fn (ParcelData $parcel): bool => $parcel->lengthCm !== null && $parcel->widthCm !== null && $parcel->heightCm !== null);

        if ($sized === []) {
            return null;
        }

        usort($sized, fn (ParcelData $a, ParcelData $b): int => ($b->lengthCm * $b->widthCm * $b->heightCm) <=> ($a->lengthCm * $a->widthCm * $a->heightCm));

        return ['length' => (int) $sized[0]->lengthCm, 'width' => (int) $sized[0]->widthCm, 'height' => (int) $sized[0]->heightCm];
    }
}
