<?php

declare(strict_types=1);

namespace WeBRTeu\FanCourier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property int $id
 * @property int|null $fan_id
 * @property string $county
 * @property string $name
 * @property string $county_key
 * @property string $name_key
 * @property string|null $agency
 * @property int $exterior_km
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FanLocality extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Config::string('fancourier.tables.localities', 'fancourier_localities');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['fan_id' => 'integer', 'exterior_km' => 'integer'];
    }
}
