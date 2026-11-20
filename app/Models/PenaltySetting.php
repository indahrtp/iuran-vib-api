<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasEffectivePeriod;
use Illuminate\Database\Eloquent\Model;

class PenaltySetting extends Model
{
    use HasAuditColumns, HasEffectivePeriod;

    protected $fillable = [
        'due_day', 'grace_days', 'rate_percent', 'max_amount',
        'coa_code', 'valid_from', 'valid_until', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'due_day'      => 'integer',
            'grace_days'   => 'integer',
            'rate_percent' => 'float',
            'max_amount'   => 'float',
            'valid_from'   => 'date:Y-m-d',
            'valid_until'  => 'date:Y-m-d',
            'is_active'    => 'boolean',
        ];
    }
}