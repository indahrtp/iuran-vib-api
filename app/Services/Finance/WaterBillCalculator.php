<?php

namespace App\Services\Finance;

use App\Enums\ChargeType;
use App\Enums\WaterTariffCategory;
use App\Models\WaterTariffSchedule;

/**
 * ASUMSI SEMENTARA (menunggu dokumen tarif air, B1):
 * - Tarif progresif: tiap tier hanya menghitung porsi pemakaian di dalam rentangnya.
 * - Tier per_m3: porsi m³ × tarif.
 * - Tier flat: dikenakan sekali jika pemakaian melewati batas bawah tier,
 *   atau jika tier dimulai dari 0 (tarif minimum, tetap ditagih walau pemakaian 0).
 * - Biaya tetap (base_fee) ditambahkan jika lebih dari 0.
 */
class WaterBillCalculator
{
    /** @return array{items: array<int, array>, total: float} */
    public function calculate(WaterTariffSchedule $schedule, float $usage, WaterTariffCategory $category): array
    {
        $items = [];

        if ($schedule->base_fee > 0) {
            $items[] = [
                'item_type'   => 'water_base',
                'ref_id'      => $schedule->id,
                'description' => 'Biaya tetap air',
                'quantity'    => 1,
                'unit_price'  => $schedule->base_fee,
                'amount'      => round($schedule->base_fee, 2),
                'coa_code'    => $schedule->base_fee_coa_code,
            ];
        }

        foreach ($schedule->tiers->sortBy('sort_order') as $tier) {
            $rate = $category === WaterTariffCategory::Commercial ? $tier->commercial_rate : $tier->general_rate;
            $upper = $tier->max_value ?? INF;
            $portion = max(0.0, min($usage, $upper) - $tier->min_value);

            if ($tier->charge_type === ChargeType::PerM3) {
                if ($portion <= 0) {
                    continue;
                }
                $quantity = $portion;
                $amount = round($portion * $rate, 2);
            } else {
                if (! ($usage > $tier->min_value || $tier->min_value == 0)) {
                    continue;
                }
                $quantity = 1;
                $amount = round($rate, 2);
            }

            $items[] = [
                'item_type'   => 'water_tier',
                'ref_id'      => $tier->id,
                'description' => $tier->name,
                'quantity'    => $quantity,
                'unit_price'  => $rate,
                'amount'      => $amount,
                'coa_code'    => $tier->coa_code,
            ];
        }

        return [
            'items' => $items,
            'total' => round(array_sum(array_column($items, 'amount')), 2),
        ];
    }
}