<?php

namespace App\Models;

use App\Enums\VehicleType;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasAuditColumns;

    protected $fillable = [
        'unit_id', 'resident_id', 'vehicle_type', 'plate_number',
        'brand', 'model', 'color', 'sticker_code', 'is_active',
    ];

    protected function casts(): array
    {
        return ['vehicle_type' => VehicleType::class, 'is_active' => 'boolean'];
    }

    public static function normalizePlate(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $value)));
    }

    public static function plateKey(string $value): string
    {
        return str_replace(' ', '', self::normalizePlate($value));
    }

    // Mengisi plate_number dan plate_key sekaligus
    public function setPlateNumberAttribute(string $value): void
    {
        $this->attributes['plate_number'] = self::normalizePlate($value);
        $this->attributes['plate_key'] = self::plateKey($value);
    }

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function resident(): BelongsTo { return $this->belongsTo(Resident::class); }
}