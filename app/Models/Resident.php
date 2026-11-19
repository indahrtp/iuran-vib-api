<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}