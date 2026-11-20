<?php

namespace App\Http\Requests\Property;

use App\Enums\VehicleType;
use App\Models\Unit;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plate_number')) {
            $this->merge(['plate_number' => Vehicle::normalizePlate($this->input('plate_number'))]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'plate_number' => ['required', 'regex:/^[A-Z]{1,2} ?\d{1,4} ?[A-Z]{0,3}$/', function ($attr, $value, $fail) use ($id) {
                $other = Vehicle::where('plate_key', Vehicle::plateKey($value))
                    ->when($id, fn ($q) => $q->where('id', '!=', $id))
                    ->first();
                if ($other) {
                    $unit = Unit::with(['street', 'block'])->find($other->unit_id);
                    $fail('TNKB sudah terdaftar di unit '.($unit ? $unit->block->name.' No. '.$unit->house_number : 'lain').'.');
                }
            }],
            'brand'        => ['required', 'string', 'max:50'],
            'model'        => ['required', 'string', 'max:50'],
            'color'        => ['required', 'string', 'max:30'],
            'resident_id'  => ['nullable', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'sticker_code' => ['nullable', 'string', 'max:30', Rule::unique('vehicles', 'sticker_code')->ignore($id)],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'plate_number.regex'    => 'Format TNKB tidak valid. Contoh: D 1234 ABC.',
            'brand.required'        => 'Merek wajib diisi.',
            'sticker_code.unique'   => 'Kode stiker sudah dipakai.',
        ];
    }
}