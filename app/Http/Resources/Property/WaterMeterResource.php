<?php

namespace App\Http\Resources\Property;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaterMeterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'unit_id'              => $this->unit_id,
            'meter_number'         => $this->meter_number,
            'initial_value'        => (float) $this->initial_value,
            'final_value'          => $this->final_value !== null ? (float) $this->final_value : null,
            'installed_at'         => $this->installed_at->toDateString(),
            'replaced_at'          => $this->replaced_at?->toDateString(),
            'replace_reason'       => $this->replace_reason?->value,
            'replace_reason_label' => $this->replace_reason?->label(),
            'is_active'            => $this->is_active,
            'status'               => $this->is_active ? 'active' : 'replaced',
        ];
    }
}