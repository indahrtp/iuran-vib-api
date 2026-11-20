<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasEffectivePeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterTariffSchedule extends Model
{
    use HasAuditColumns, HasEffectivePeriod;

    protected $fillable = ['base_fee', 'base_fee_coa_code', 'valid_from', 'valid_until', 'is_active'];

    protected function casts(): array
    {
        return [
            'base_fee'    => 'float',
            'valid_from'  => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'is_active'   => 'boolean',
        ];
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(WaterTariffTier::class, 'schedule_id')->orderBy('sort_order');
    }
}