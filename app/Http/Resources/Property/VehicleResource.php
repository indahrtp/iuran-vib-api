<?php

namespace App\Http\Resources\Property;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'unit_id'            => $this->unit_id,
            'resident_id'        => $this->resident_id,
            'resident_name'      => $this->whenLoaded('resident', fn () => $this->resident?->name),
            'vehicle_type'       => $this->vehicle_type->value,
            'vehicle_type_label' => $this->vehicle_type->label(),
            'plate_number'       => $this->plate_number,
            'brand'              => $this->brand,
            'model'              => $this->model,
            'color'              => $this->color,
            'sticker_code'       => $this->sticker_code,
            'is_active'          => $this->is_active,
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}