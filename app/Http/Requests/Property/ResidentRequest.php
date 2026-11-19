<?php

namespace App\Http\Requests\Property;

use App\Enums\Gender;
use App\Models\Resident;
use App\Services\Shared\RegionService;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResidentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('nik')) {
            $merge['nik'] = preg_replace('/[\s\-]/', '', (string) $this->input('nik'));
        }
        foreach (['phone', 'phone_2'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = PhoneNumber::normalize($this->input($field));
            }
        }

        $this->merge($merge);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'nik' => ['required', 'digits:16', function ($attribute, $value, $fail) use ($id) {
                $query = Resident::withTrashed()->where('nik_hash', Resident::hashNik($value));
                if ($id) {
                    $query->where('id', '!=', $id);
                }
                if ($query->exists()) {
                    $fail('NIK sudah terdaftar.');
                }
            }],
            'name'         => ['required', 'string', 'max:150'],
            'gender'       => ['required', Rule::enum(Gender::class)],
            'birth_date'   => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'street_name'  => ['required', 'string', 'max:150'],
            'house_number' => ['required', 'string', 'max:20'],
            'rt'           => ['required', 'digits_between:1,3'],
            'rw'           => ['required', 'digits_between:1,3'],
            'postal_code'  => ['required', 'digits:5'],
            'village_code' => ['required', 'string', Rule::exists(RegionService::table('villages'), 'code')],
            'phone'        => ['required', 'regex:/^628\d{7,12}$/'],
            'phone_2'      => ['nullable', 'regex:/^628\d{7,12}$/'],
            'email'        => ['nullable', 'email', 'max:150'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.digits'         => 'NIK harus 16 digit.',
            'phone.regex'        => 'Nomor ponsel tidak valid.',
            'phone_2.regex'      => 'Nomor ponsel 2 tidak valid.',
            'village_code.exists' => 'Kelurahan tidak ditemukan.',
        ];
    }
}
