<?php

namespace Database\Factories;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nik'           => fake()->unique()->numerify('################'),
            'name'          => fake()->name(),
            'gender'        => fake()->randomElement(['male', 'female']),
            'birth_date'    => fake()->date('Y-m-d', '2000-01-01'),
            'street_name'   => 'Jl. Melati',
            'house_number'  => (string) fake()->numberBetween(1, 99),
            'rt'            => '001',
            'rw'            => '002',
            'postal_code'   => '40391',
            'province_code' => '32',
            'city_code'     => '3204',
            'district_code' => '320401',
            'village_code'  => '3204012001',
            'phone'         => '62812'.fake()->numerify('#######'),
            'is_active'     => true,
        ];
    }
}
