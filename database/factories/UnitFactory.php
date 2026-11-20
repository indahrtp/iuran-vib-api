<?php

namespace Database\Factories;

use App\Models\{Block, Resident, Street, Village, Zone};
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'street_id'             => Street::firstOrCreate(['code' => 'MLT'], ['name' => 'Jl. Melati'])->id,
            'block_id'              => Block::firstOrCreate(['code' => 'A'], ['name' => 'Blok A'])->id,
            'zone_id'               => Zone::firstOrCreate(['code' => 'Z1'], ['name' => 'Zona 1'])->id,
            'village_id'            => Village::firstOrCreate(['code' => 'KEL-01'], ['name' => 'Kelurahan Contoh'])->id,
            'house_number'          => (string) fake()->unique()->numberBetween(1, 9999),
            'owner_id'              => Resident::factory(),
            'fee_guarantor'         => 'owner',
            'water_tariff_category' => 'general',
            'land_area'             => 120,
            'is_vacant_land'        => false,
            'building_area'         => 90,
            'building_status'       => 'occupied',
            'is_active'             => true,
        ];
    }
}