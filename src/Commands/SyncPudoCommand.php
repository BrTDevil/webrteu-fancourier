<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier\Commands;

use Illuminate\Console\Command;
use WeBRTeu\FanCourier\FanCourierCarrier;
use WeBRTeu\FanCourier\Models\FanPickupPoint;
use WeBRTeu\Shipping\Enums\PickupPointType;

class SyncPudoCommand extends Command
{
    protected $signature = 'fancourier:sync-pudo';

    protected $description = 'Sincronizează punctele FANbox / PayPoint în fancourier_pickup_points';

    public function handle(FanCourierCarrier $fan): int
    {
        $points = $fan->remotePickupPoints();

        foreach ($points as $point) {
            FanPickupPoint::query()->updateOrCreate(['point_id' => $point->id], [
                'type' => $point->type === PickupPointType::Pudo ? 'paypoint' : 'fanbox',
                'name' => $point->name,
                'county' => $point->county,
                'city' => $point->city,
                'address' => $point->address,
                'postal_code' => $point->postalCode,
                'lat' => $point->lat,
                'lng' => $point->lng,
                'schedule' => $point->schedule,
                'supports_cod' => $point->supportsCod,
                'active' => true,
            ]);
        }

        $deactivated = $points->isEmpty() ? 0 : FanPickupPoint::query()->whereNotIn('point_id', $points->pluck('id')->all())->update(['active' => false]);

        $this->info("Puncte sincronizate: {$points->count()}, dezactivate: {$deactivated}.");

        return self::SUCCESS;
    }
}
