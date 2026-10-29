<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier\Commands;

use Illuminate\Console\Command;
use WeBRTeu\FanCourier\FanCourierCarrier;
use WeBRTeu\Shipping\Data\ServiceData;

class ServicesCommand extends Command
{
    protected $signature = 'fancourier:services';

    protected $description = 'Afișează serviciile contului FAN Courier';

    public function handle(FanCourierCarrier $fan): int
    {
        $this->table(['Cod', 'Descriere', 'FANbox'], $fan->services()->map(fn (ServiceData $service): array => [
            $service->code,
            $service->name,
            $service->pickupPoint ? 'da' : 'nu',
        ])->all());

        return self::SUCCESS;
    }
}
