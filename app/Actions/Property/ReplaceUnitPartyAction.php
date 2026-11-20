<?php

namespace App\Actions\Property;

use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplaceUnitPartyAction
{
    /** $party: 'owner' atau 'occupant'. $residentId null = kosongkan (hanya penghuni) */
    public function execute(Unit $unit, string $party, ?int $residentId, string $startDate, bool $setAsGuarantor = false): Unit
    {
        $isOwner = $party === 'owner';
        $column = $isOwner ? 'owner_id' : 'occupant_id';
        $history = $isOwner ? UnitOwnership::class : UnitOccupancy::class;
        $label = $isOwner ? 'Pemilik' : 'Penghuni';
        $start = Carbon::parse($startDate)->startOfDay();

        return DB::transaction(function () use ($unit, $party, $residentId, $start, $setAsGuarantor, $column, $history, $label) {
            $unit = Unit::whereKey($unit->id)->lockForUpdate()->firstOrFail();

            if ((int) $unit->{$column} === (int) $residentId) {
                throw ValidationException::withMessages([
                    'resident_id' => ["{$label} baru sama dengan ".strtolower($label).' saat ini.'],
                ]);
            }

            if ($residentId === null && $unit->fee_guarantor->value === $party) {
                throw ValidationException::withMessages([
                    'resident_id' => ["{$label} adalah penjamin iuran. Ganti penjamin terlebih dahulu."],
                ]);
            }

            $current = $history::where('unit_id', $unit->id)->whereNull('end_date')
                ->orderByDesc('start_date')->first();

            if ($current && $start->lt($current->start_date)) {
                throw ValidationException::withMessages([
                    'start_date' => ['Tanggal mulai tidak boleh sebelum '.$current->start_date->format('d-m-Y').'.'],
                ]);
            }

            // Tutup riwayat lama sehari sebelum tanggal mulai yang baru
            $current?->update([
                'end_date' => $start->copy()->subDay()->max($current->start_date)->toDateString(),
            ]);

            if ($residentId !== null) {
                $history::create([
                    'unit_id' => $unit->id, 'resident_id' => $residentId, 'start_date' => $start->toDateString(),
                ]);
            }

            $unit->{$column} = $residentId;
            if ($setAsGuarantor && $residentId !== null) {
                $unit->fee_guarantor = $party;
            }
            $unit->save();

            return $unit;
        });
    }
}