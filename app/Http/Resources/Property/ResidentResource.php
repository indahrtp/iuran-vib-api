<?php

namespace App\Http\Resources\Property;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResidentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    protected ?array $region = null;

    public function withRegion(?array $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'nik_masked'    => $this->maskedNik(),   // NIK lengkap hanya lewat endpoint khusus
            'name'          => $this->name,
            'gender'        => $this->gender->value,
            'gender_label'  => $this->gender->label(),
            'birth_date'    => $this->birth_date?->toDateString(),
            'age'           => $this->birth_date?->age,
            'street_name'   => $this->street_name,
            'house_number'  => $this->house_number,
            'rt'            => $this->rt,
            'rw'            => $this->rw,
            'postal_code'   => $this->postal_code,
            'province_code' => $this->province_code,
            'city_code'     => $this->city_code,
            'district_code' => $this->district_code,
            'village_code'  => $this->village_code,
            'region'        => $this->when($this->region !== null, $this->region),
            'phone'         => $this->phone,
            'phone_2'       => $this->phone_2,
            'email'         => $this->email,
            'has_photo'     => filled($this->photo_path),
            'photo_url'     => filled($this->photo_path) ? url("/api/v1/residents/{$this->id}/photo") : null,
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
