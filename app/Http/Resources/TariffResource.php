<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TariffResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->attributesToArray(),
            'period_status' => $this->resource->periodStatus(),
            'tiers' => $this->whenLoaded('tiers', fn () => $this->resource->tiers
                ->sortBy('sort_order')->values()
                ->map(fn ($t) => [...$t->attributesToArray(), 'charge_type_label' => $t->charge_type->label()])),
        ];
    }
}
