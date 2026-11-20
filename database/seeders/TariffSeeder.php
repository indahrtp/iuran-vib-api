<?php

namespace Database\Seeders;

use App\Models\{IplRate, PenaltySetting, WaterTariffSchedule};
use Illuminate\Database\Seeder;

class TariffSeeder extends Seeder
{
    public function run(): void
    {
        // Hanya untuk pengembangan lokal. NILAI INI CONTOH, bukan tarif pengelola
        if (! app()->environment('local') || IplRate::exists()) {
            return;
        }

        IplRate::create(['rate_per_m2' => 1000, 'coa_code' => '4-1001', 'valid_from' => '2026-01-01']);

        PenaltySetting::create([
            'due_day' => 20, 'grace_days' => 5, 'rate_percent' => 2, 'max_amount' => null,
            'coa_code' => '4-1003', 'valid_from' => '2026-01-01', 'is_active' => true,
        ]);

        $schedule = WaterTariffSchedule::create(['base_fee' => 0, 'valid_from' => '2026-01-01']);
        foreach ([
            ['Tier 1 (0-10 m³)', 0, 10, 'flat', 30000, 45000],
            ['Tier 2 (11-20 m³)', 10, 20, 'per_m3', 3500, 5000],
            ['Tier 3 (di atas 20 m³)', 20, null, 'per_m3', 5000, 7500],
        ] as $i => [$name, $min, $max, $type, $general, $commercial]) {
            $schedule->tiers()->create([
                'sort_order' => $i + 1, 'name' => $name, 'min_value' => $min, 'max_value' => $max,
                'charge_type' => $type, 'general_rate' => $general, 'commercial_rate' => $commercial,
                'coa_code' => '4-1002',
            ]);
        }
    }
}