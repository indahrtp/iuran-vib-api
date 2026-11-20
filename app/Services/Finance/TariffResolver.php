<?php

namespace App\Services\Finance;

use App\Models\IplRate;
use App\Models\OtherFee;
use App\Models\PenaltySetting;
use App\Models\WaterTariffSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Mencari tarif yang berlaku pada tanggal tertentu. Dipakai oleh generate tagihan (Tahap 7) */
class TariffResolver
{
    public function iplRate(?Carbon $date = null): ?IplRate
    {
        return IplRate::effectiveOn($date)->orderByDesc('valid_from')->first();
    }

    /** Null berarti denda tidak berlaku (belum diatur atau dinonaktifkan) */
    public function penaltySetting(?Carbon $date = null): ?PenaltySetting
    {
        return PenaltySetting::effectiveOn($date)->orderByDesc('valid_from')->first();
    }

    public function waterSchedule(?Carbon $date = null): ?WaterTariffSchedule
    {
        return WaterTariffSchedule::with('tiers')->effectiveOn($date)->orderByDesc('valid_from')->first();
    }

    /** @return Collection<int, OtherFee> */
    public function otherFees(?Carbon $date = null): Collection
    {
        return OtherFee::effectiveOn($date)->orderBy('name')->get();
    }
}