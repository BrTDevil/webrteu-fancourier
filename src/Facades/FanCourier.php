<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier\Facades;

use Illuminate\Support\Facades\Facade;
use WeBRTeu\FanCourier\FanCourierCarrier;

/**
 * @method static \WeBRTeu\Shipping\Data\AwbData createShipment(\WeBRTeu\Shipping\Data\ShipmentData $shipment)
 * @method static \WeBRTeu\Shipping\Data\TrackingData track(string $awb)
 * @method static string orderPickup(\WeBRTeu\Shipping\Data\PickupRequestData $request)
 * @method static bool cancelPickup(string $orderId)
 * @method static \WeBRTeu\FanCourier\FanCourierClient client()
 *
 * @see FanCourierCarrier
 */
class FanCourier extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FanCourierCarrier::class;
    }
}
