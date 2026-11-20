<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resident extends Model
{
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'nik', 'name', 'gender', 'birth_date',
        'street_name', 'house_number', 'rt', 'rw', 'postal_code',
        'province_code', 'city_code', 'district_code', 'village_code',
        'phone', 'phone_2', 'email', 'photo_path', 'is_active',
    ];

    protected $hidden = ['nik', 'nik_hash'];

    protected function casts(): array
    {
        return [
            'nik'        => 'encrypted',
            'gender'     => Gender::class,
            'birth_date' => 'date',
            'is_active'  => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // nik_hash selalu mengikuti nik, tidak pernah diisi manual
        static::saving(function (Resident $resident) {
            if ($resident->isDirty('nik') || ! $resident->exists) {
                $resident->nik_hash = self::hashNik($resident->nik);
            }
        });
    }

    /** Catatan: mengganti APP_KEY membuat NIK tidak bisa didekripsi dan hash tidak cocok. Cadangkan key-nya. */
    public static function hashNik(string $nik): string
    {
        return hash_hmac('sha256', $nik, (string) config('app.key'));
    }

    public function maskedNik(): string
    {
        $nik = (string) $this->nik;

        return substr($nik, 0, 4).str_repeat('*', max(strlen($nik) - 8, 0)).substr($nik, -4);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $query->when($term !== '', function (Builder $q) use ($term) {
            $digits = preg_replace('/\D/', '', $term);
            if (str_starts_with($digits, '0')) {
                $digits = '62'.substr($digits, 1);
            }

            $q->where(function (Builder $w) use ($term, $digits) {
                $w->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");

                if ($digits !== '') {
                    $w->orWhere('phone', 'like', "%{$digits}%")
                      ->orWhere('phone_2', 'like', "%{$digits}%");
                }
                if (preg_match('/^\d{16}$/', $term)) {
                    $w->orWhere('nik_hash', self::hashNik($term));
                }
            });
        });
    }

    public function ownedUnits(): HasMany { return $this->hasMany(Unit::class, 'owner_id'); }
    public function occupiedUnits(): HasMany { return $this->hasMany(Unit::class, 'occupant_id'); }

    /** Muat penanda pemilik/penghuni dalam satu query (hindari N+1) */
    public function scopeWithProfileStatus(Builder $query): Builder
    {
        return $query->withExists(['ownedUnits as is_owner', 'occupiedUnits as is_occupant']);
    }

    public function scopeHavingProfileStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, function (Builder $q) use ($status) {
            match ($status) {
                'owner'    => $q->whereHas('ownedUnits')->whereDoesntHave('occupiedUnits'),
                'occupant' => $q->whereHas('occupiedUnits')->whereDoesntHave('ownedUnits'),
                'both'     => $q->whereHas('ownedUnits')->whereHas('occupiedUnits'),
                default    => null,
            };
        });
    }

    /** 'owner' | 'occupant' | 'both' | null. Dihitung dari unit, tidak disimpan */
    public function profileStatus(): ?string
    {
        $isOwner = array_key_exists('is_owner', $this->attributes)
            ? (bool) $this->attributes['is_owner'] : $this->ownedUnits()->exists();
        $isOccupant = array_key_exists('is_occupant', $this->attributes)
            ? (bool) $this->attributes['is_occupant'] : $this->occupiedUnits()->exists();

        return match (true) {
            $isOwner && $isOccupant => 'both',
            $isOwner => 'owner',
            $isOccupant => 'occupant',
            default => null,
        };
    }
}