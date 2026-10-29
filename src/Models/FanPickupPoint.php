<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property int $id
 * @property string $point_id
 * @property string $type
 * @property string $name
 * @property string $county
 * @property string $city
 * @property string $address
 * @property string|null $postal_code
 * @property float|null $lat
 * @property float|null $lng
 * @property array<array-key, mixed>|null $schedule
 * @property bool $supports_cod
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FanPickupPoint extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Config::string('fancourier.tables.pickup_points', 'fancourier_pickup_points');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'schedule' => 'array',
            'supports_cod' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
