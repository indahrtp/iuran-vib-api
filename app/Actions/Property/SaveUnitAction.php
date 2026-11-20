<?php

namespace App\Actions\Property;

use App\Enums\BuildingStatus;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveUnitAction
{
    public function execute(array $data, ?Unit $unit = null): Unit
    {
        $startDate = $data['start_date'] ?? now()->toDateString();
        $ownerId = $data['owner_id'] ?? null;
        $occupantId = $data['occupant_id'] ?? null;
        unset($data['start_date']);

        // Aturan Tanah Kosong (lapisan kedua setelah FormRequest)
        if (! empty($data['is_vacant_land'])) {
            $data['building_area'] = null;
            $data['building_status'] = null;
        }
        if (($data['building_status'] ?? null) !== BuildingStatus::Rented->value) {
            $data['rental_type'] = null;
        }

        return DB::transaction(function () use ($data, $unit, $ownerId, $occupantId, $startDate) {
            if ($unit) {
                $unit->update(Arr::except($data, ['owner_id', 'occupant_id']));

                return $unit->refresh();
            }

            $unit = Unit::create($data);

            if ($ownerId) {
                UnitOwnership::create(['unit_id' => $unit->id, 'resident_id' => $ownerId, 'start_date' => $startDate]);
            }
            if ($occupantId) {
                UnitOccupancy::create(['unit_id' => $unit->id, 'resident_id' => $occupantId, 'start_date' => $startDate]);
            }

            return $unit->refresh();
        });
    }
}