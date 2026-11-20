<?php

namespace App\Models;

use App\Enums\ChargeType;
use Illuminate\Database\Eloquent\Model;

class WaterTariffTier extends Model
{
    protected $fillable = [
        'schedule_id', 'sort_order', 'name', 'min_value', 'max_value',
        'charge_type', 'general_rate', 'commercial_rate', 'coa_code',
    ];

    protected function casts(): array
    {
        return [
            'min_value'       => 'float',
            'max_value'       => 'float',
            'general_rate'    => 'float',
            'commercial_rate' => 'float',
            'charge_type'     => ChargeType::class,
        ];
    }
}