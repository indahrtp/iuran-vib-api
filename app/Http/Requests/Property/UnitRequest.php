<?php

namespace App\Http\Requests\Property;

use App\Enums\BuildingStatus;
use App\Enums\FeeGuarantor;
use App\Enums\RentalType;
use App\Enums\WaterTariffCategory;
use App\Models\Block;
use App\Models\Street;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dijaga middleware permission di route
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $isCreate = $this->isMethod('POST');
        $vacant = $this->boolean('is_vacant_land');
        $rented = ! $vacant && $this->input('building_status') === BuildingStatus::Rented->value;
        $resident = Rule::exists('residents', 'id')->whereNull('deleted_at');

        $rules = [
            'name'         => ['nullable', 'string', 'max:100'],
            'street_id'    => ['required', Rule::exists('streets', 'id')],
            'block_id'     => ['required', Rule::exists('blocks', 'id')],
            'zone_id'      => ['required', Rule::exists('zones', 'id')],
            'village_id'   => ['required', Rule::exists('villages', 'id')],
            'house_number' => ['required', 'string', 'max:20', function ($attr, $value, $fail) use ($id) {
                $street = $this->input('street_id');
                $block = $this->input('block_id');
                if (! is_numeric($street) || ! is_numeric($block)) {
                    return;
                }
                $exists = Unit::where('street_id', $street)->where('block_id', $block)
                    ->where('house_number', $value)
                    ->when($id, fn ($q) => $q->where('id', '!=', $id))
                    ->exists();
                if ($exists) {
                    $blockName = Block::find($block)?->name;
                    $streetName = Street::find($street)?->name;
                    $fail("Unit {$blockName} No. {$value} di {$streetName} sudah terdaftar.");
                }
            }],
            'latitude'              => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'             => ['nullable', 'numeric', 'between:-180,180'],
            'fee_guarantor'         => ['required', Rule::enum(FeeGuarantor::class)],
            'water_tariff_category' => ['required', Rule::enum(WaterTariffCategory::class)],
            'land_area'             => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'is_vacant_land'        => ['required', 'boolean'],

            // Aturan Tanah Kosong: tiga kolom ini wajib kosong jika tanah kosong
            'building_area'   => $vacant ? ['prohibited'] : ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'building_status' => $vacant ? ['prohibited'] : ['required', Rule::enum(BuildingStatus::class)],
            'rental_type'     => $rented ? ['required', Rule::enum(RentalType::class)] : ['prohibited'],
            'is_active'       => ['sometimes', 'boolean'],
        ];

        if ($isCreate) {
            // Saat edit, pemilik dan penghuni hanya berubah lewat endpoint "ganti"
            $rules['owner_id'] = ['nullable', $resident];
            $rules['occupant_id'] = ['nullable', $resident];
            $rules['start_date'] = ['nullable', 'date', 'before_or_equal:today'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->has('fee_guarantor')) {
                return;
            }

            if ($this->isMethod('POST')) {
                $ownerId = $this->input('owner_id');
                $occupantId = $this->input('occupant_id');
            } else {
                $unit = Unit::find($this->route('id'));
                $ownerId = $unit?->owner_id;
                $occupantId = $unit?->occupant_id;
            }

            $guarantor = $this->input('fee_guarantor');
            if ($guarantor === 'owner' && ! $ownerId) {
                $v->errors()->add('fee_guarantor', 'Penjamin iuran (pemilik) belum dipilih.');
            }
            if ($guarantor === 'occupant' && ! $occupantId) {
                $v->errors()->add('fee_guarantor', 'Penjamin iuran (penghuni) belum dipilih.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'building_area.prohibited'   => 'Luas bangunan harus kosong jika tanah kosong.',
            'building_status.prohibited' => 'Status bangunan harus kosong jika tanah kosong.',
            'rental_type.prohibited'     => 'Jenis sewa hanya boleh diisi jika status bangunan Disewakan.',
            'building_area.required'     => 'Luas bangunan wajib diisi.',
            'building_status.required'   => 'Status bangunan wajib dipilih.',
            'rental_type.required'       => 'Jenis sewa wajib dipilih.',
            'land_area.required'         => 'Luas tanah wajib diisi.',
        ];
    }
}