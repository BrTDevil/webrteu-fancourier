<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier;

use WeBRTeu\Shipping\Enums\ShipmentStatus;
use WeBRTeu\Shipping\Support\StatusResolver;

/**
 * Codurile de eveniment FAN Courier → ShipmentStatus.
 *
 * ⚠️ Neverificat pe API real: doar codurile din documentația publică; restul cad pe cuvintele cheie din StatusResolver
 * și se completează din tracking real (log `shipping.unknown_status`).
 */
final class FanCourierStatusMap
{
    /**
     * @var array<array-key, ShipmentStatus>
     */
    public const MAP = [
        'S2' => ShipmentStatus::Delivered,
    ];

    public static function resolve(string $code, string $label): ShipmentStatus
    {
        return StatusResolver::resolve('fancourier', $code, $label, self::MAP);
    }
}
