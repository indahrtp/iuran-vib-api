<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasEffectivePeriod;
use Illuminate\Database\Eloquent\Model;

class OtherFee extends Model
{
    use HasAuditColumns, HasEffectivePeriod;

    protected $fillable = ['name', 'amount', 'coa_code', 'valid_from', 'valid_until', 'is_active'];

    protected function casts(): array
    {
        return [
            'amount'      => 'float',
            'valid_from'  => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'is_active'   => 'boolean',
        ];
    }
}