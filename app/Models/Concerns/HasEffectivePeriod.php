<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait HasEffectivePeriod
{
    /** Tarif yang aktif dan berlaku pada tanggal tertentu (default hari ini) */
    public function scopeEffectiveOn(Builder $query, ?Carbon $date = null): Builder
    {
        $d = ($date ?? now())->toDateString();

        return $query->where('is_active', true)
            ->whereDate('valid_from', '<=', $d)
            ->where(fn ($w) => $w->whereNull('valid_until')->orWhereDate('valid_until', '>=', $d));
    }

    /** Sudah mulai berlaku (atau sudah lewat). Yang sudah berlaku tidak boleh diubah nilainya */
    public function hasStarted(): bool
    {
        return $this->valid_from->toDateString() <= now()->toDateString();
    }

    /** 'inactive' | 'scheduled' | 'expired' | 'active' */
    public function periodStatus(): string
    {
        $today = now()->toDateString();

        return match (true) {
            ! $this->is_active => 'inactive',
            $this->valid_from->toDateString() > $today => 'scheduled',
            $this->valid_until !== null && $this->valid_until->toDateString() < $today => 'expired',
            default => 'active',
        };
    }
}