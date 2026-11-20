<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasEffectivePeriod;
use Illuminate\Database\Eloquent\Model;

class IplRate extends Model
{
    use HasAuditColumns, HasEffectivePeriod;

    protected $fillable = ['rate_per_m2', 'coa_code', 'valid_from', 'valid_until', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate_per_m2' => 'float',
            'valid_from'  => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'is_active'   => 'boolean',
        ];
    }
}