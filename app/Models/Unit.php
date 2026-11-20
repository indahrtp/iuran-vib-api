<?php

namespace App\Models;

use App\Enums\BuildingStatus;
use App\Enums\FeeGuarantor;
use App\Enums\RentalType;
use App\Enums\WaterTariffCategory;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Unit extends Model
{
    use HasAuditColumns, HasFactory;

    protected $fillable = [
        'name', 'street_id', 'block_id', 'house_number', 'zone_id', 'village_id',
        'latitude', 'longitude', 'owner_id', 'occupant_id',
        'fee_guarantor', 'water_tariff_category',
        'land_area', 'is_vacant_land', 'building_area', 'building_status', 'rental_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fee_guarantor'         => FeeGuarantor::class,
            'water_tariff_category' => WaterTariffCategory::class,
            'building_status'       => BuildingStatus::class,
            'rental_type'           => RentalType::class,
            'land_area'             => 'decimal:2',
            'building_area'         => 'decimal:2',
            'is_vacant_land'        => 'boolean',
            'is_active'             => 'boolean',
        ];
    }

    public function street(): BelongsTo { return $this->belongsTo(Street::class); }
    public function block(): BelongsTo { return $this->belongsTo(Block::class); }
    public function zone(): BelongsTo { return $this->belongsTo(Zone::class); }
    public function village(): BelongsTo { return $this->belongsTo(Village::class); }
    public function owner(): BelongsTo { return $this->belongsTo(Resident::class, 'owner_id'); }
    public function occupant(): BelongsTo { return $this->belongsTo(Resident::class, 'occupant_id'); }
    public function ownerships(): HasMany { return $this->hasMany(UnitOwnership::class); }
    public function occupancies(): HasMany { return $this->hasMany(UnitOccupancy::class); }
    public function vehicles(): HasMany { return $this->hasMany(Vehicle::class); }
    public function waterMeters(): HasMany { return $this->hasMany(WaterMeter::class); }

    public function activeMeter(): HasOne
    {
        return $this->hasOne(WaterMeter::class)->where('is_active', true);
    }

    /** Contoh: "Jl. Melati Blok A No. 12". Butuh relasi street dan block sudah dimuat */
    public function addressLabel(): string
    {
        return "{$this->street->name} {$this->block->name} No. {$this->house_number}";
    }

    public function guarantorId(): ?int
    {
        return $this->fee_guarantor === FeeGuarantor::Owner ? $this->owner_id : $this->occupant_id;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $query->when($term !== '', function (Builder $q) use ($term) {
            $q->where(function (Builder $w) use ($term) {
                $w->where('house_number', 'like', "%{$term}%")
                  ->orWhere('name', 'like', "%{$term}%")
                  ->orWhereHas('owner', fn ($o) => $o->where('name', 'like', "%{$term}%"))
                  ->orWhereHas('occupant', fn ($o) => $o->where('name', 'like', "%{$term}%"));
            });
        });
    }
}