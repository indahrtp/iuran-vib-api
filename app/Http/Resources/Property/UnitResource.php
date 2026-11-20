<?php

namespace App\Http\Resources\Property;

use App\Http\Resources\MasterResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $party = fn ($resident) => $resident ? [
            'id' => $resident->id, 'name' => $resident->name,
            'nik_masked' => $resident->maskedNik(), 'phone' => $resident->phone,
        ] : null;

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'address'       => $this->whenLoaded('street', fn () => $this->addressLabel()),
            'house_number'  => $this->house_number,
            'street_id'     => $this->street_id,
            'block_id'      => $this->block_id,
            'zone_id'       => $this->zone_id,
            'village_id'    => $this->village_id,
            'street'        => new MasterResource($this->whenLoaded('street')),
            'block'         => new MasterResource($this->whenLoaded('block')),
            'zone'          => new MasterResource($this->whenLoaded('zone')),
            'village'       => new MasterResource($this->whenLoaded('village')),
            'latitude'      => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude'     => $this->longitude !== null ? (float) $this->longitude : null,

            'owner_id'      => $this->owner_id,
            'occupant_id'   => $this->occupant_id,
            'owner'         => $this->whenLoaded('owner', fn () => $party($this->owner)),
            'occupant'      => $this->whenLoaded('occupant', fn () => $party($this->occupant)),
            'fee_guarantor'       => $this->fee_guarantor->value,
            'fee_guarantor_label' => $this->fee_guarantor->label(),
            'guarantor_id'        => $this->guarantorId(),
            'water_tariff_category'       => $this->water_tariff_category->value,
            'water_tariff_category_label' => $this->water_tariff_category->label(),

            'land_area'     => (float) $this->land_area,
            'is_vacant_land' => $this->is_vacant_land,
            'building_area' => $this->building_area !== null ? (float) $this->building_area : null,
            'building_status'       => $this->building_status?->value,
            'building_status_label' => $this->building_status?->label(),
            'rental_type'           => $this->rental_type?->value,
            'rental_type_label'     => $this->rental_type?->label(),

            'active_meter'  => $this->whenLoaded('activeMeter', fn () => $this->activeMeter ? [
                'id' => $this->activeMeter->id,
                'meter_number' => $this->activeMeter->meter_number,
                'initial_value' => (float) $this->activeMeter->initial_value,
                'installed_at' => $this->activeMeter->installed_at->toDateString(),
            ] : null),

            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}