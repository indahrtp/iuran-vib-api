<?php

namespace App\Models;

use App\Enums\MeterReplaceReason;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;

class WaterMeter extends Model
{
    use HasAuditColumns;

    protected $fillable = [
        'unit_id', 'meter_number', 'initial_value', 'final_value',
        'installed_at', 'replaced_at', 'replace_reason', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'initial_value'  => 'decimal:2',
            'final_value'    => 'decimal:2',
            'installed_at'   => 'date',
            'replaced_at'    => 'date',
            'replace_reason' => MeterReplaceReason::class,
            'is_active'      => 'boolean',
        ];
    }
}