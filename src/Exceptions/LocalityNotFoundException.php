<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier\Exceptions;

use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;

class LocalityNotFoundException extends ShipmentValidationException
{
    public static function for(string $county, string $city): self
    {
        return new self(
            sprintf('Localitatea [%s, jud. %s] nu există în nomenclatorul FAN Courier. Rulează php artisan fancourier:sync-locations.', $city, $county),
            ['recipient.address.locality' => [$city]],
        );
    }
}
