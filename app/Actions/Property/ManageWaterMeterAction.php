<?php

namespace App\Actions\Property;

use App\Models\Unit;
use App\Models\WaterMeter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ManageWaterMeterAction
{
    public function install(Unit $unit, array $data, int $userId): WaterMeter
    {
        if ($unit->is_vacant_land) {
            throw ValidationException::withMessages(['unit' => ['Tanah kosong tidak memiliki meteran.']]);
        }
        if (WaterMeter::where('unit_id', $unit->id)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['unit' => ['Unit sudah memiliki meteran aktif. Gunakan penggantian meteran.']]);
        }

        $meter = WaterMeter::create([...$data, 'unit_id' => $unit->id, 'is_active' => true]);
        Log::info('Meteran air dipasang', ['unit_id' => $unit->id, 'meter_id' => $meter->id, 'user_id' => $userId]);

        return $meter->refresh();
    }

    public function replace(Unit $unit, array $data, int $userId): WaterMeter
    {
        return DB::transaction(function () use ($unit, $data, $userId) {
            $old = WaterMeter::where('unit_id', $unit->id)->where('is_active', true)->lockForUpdate()->first();

            if (! $old) {
                throw ValidationException::withMessages(['unit' => ['Unit belum memiliki meteran aktif. Gunakan pemasangan meteran.']]);
            }

            // Tahap 6: pembanding akan diganti dengan bacaan terakhir dari catat meter
            if ((float) $data['old_final_value'] < (float) $old->initial_value) {
                throw ValidationException::withMessages([
                    'old_final_value' => ['Angka akhir tidak boleh lebih kecil dari bacaan terakhir.'],
                ]);
            }
            if ($data['replaced_at'] < $old->installed_at->toDateString()) {
                throw ValidationException::withMessages([
                    'replaced_at' => ['Tanggal penggantian tidak boleh sebelum tanggal pasang meteran lama.'],
                ]);
            }

            // Nonaktifkan dulu agar meteran baru boleh menjadi satu-satunya yang aktif
            $old->update([
                'final_value'    => $data['old_final_value'],
                'replaced_at'    => $data['replaced_at'],
                'replace_reason' => $data['replace_reason'],
                'is_active'      => false,
            ]);

            $new = WaterMeter::create([
                'unit_id'       => $unit->id,
                'meter_number'  => $data['meter_number'],
                'initial_value' => $data['initial_value'],
                'installed_at'  => $data['replaced_at'],
                'is_active'     => true,
            ]);

            // Sementara ke log. Dipindah ke audit log (activity_log) di tahap Sistem
            Log::info('Meteran air diganti', [
                'unit_id' => $unit->id, 'old_meter_id' => $old->id, 'new_meter_id' => $new->id,
                'old_final_value' => $data['old_final_value'], 'reason' => $data['replace_reason'], 'user_id' => $userId,
            ]);

            return $new->refresh();
        });
    }
}