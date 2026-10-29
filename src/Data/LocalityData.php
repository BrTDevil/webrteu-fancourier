<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier\Data;

/**
 * Județul și localitatea exact cum le cere nomenclatorul FAN.
 */
final readonly class LocalityData
{
    public function __construct(
        public string $county,
        public string $locality,
        public ?int $id = null,
    ) {}
}
