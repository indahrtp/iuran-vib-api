<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Enums\ChargeType;
use App\Enums\WaterTariffCategory;
use App\Models\WaterTariffSchedule;
use App\Services\Finance\TariffResolver;
use App\Services\Finance\WaterBillCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class WaterTariffController extends VersionedTariffController
{
    protected string $model = WaterTariffSchedule::class;
    protected array $with = ['tiers'];

    protected function rules(): array
    {
        return [
            'base_fee'          => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'base_fee_coa_code' => ['nullable', 'string', 'max:30', Rule::exists('chart_of_accounts', 'code')->where('is_active', true)],
            'valid_from'        => ['required', 'date'],
            'tiers'             => ['bail', 'required', 'array', 'min:1', 'max:20', fn ($attr, $tiers, $fail) => $this->validateContinuity($tiers, $fail)],
            'tiers.*.name'      => ['required', 'string', 'max:100'],
            'tiers.*.min_value' => ['required', 'numeric', 'min:0'],
            'tiers.*.max_value' => ['nullable', 'numeric', 'gt:0'],
            'tiers.*.charge_type'     => ['required', Rule::enum(ChargeType::class)],
            'tiers.*.general_rate'    => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'tiers.*.commercial_rate' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'tiers.*.coa_code'        => $this->coaRule(),
        ];
    }

    /** Tier harus berurutan tanpa celah: mulai 0, batas bawah = batas atas sebelumnya, tier terakhir tak terbatas */
    private function validateContinuity(array $tiers, callable $fail): void
    {
        $tiers = array_values($tiers);
        $prevMax = null;

        foreach ($tiers as $i => $tier) {
            if (! is_array($tier)) {
                return;
            }
            $n = $i + 1;
            $min = (float) ($tier['min_value'] ?? -1);
            $max = isset($tier['max_value']) ? (float) $tier['max_value'] : null;
            $isLast = $i === count($tiers) - 1;

            if ($i === 0 && abs($min) > 0.004) {
                $fail('Tier 1 harus dimulai dari 0 m³.');
                return;
            }
            if ($i > 0 && abs($min - (float) $prevMax) > 0.004) {
                $fail("Batas bawah tier {$n} harus sama dengan batas atas tier sebelumnya.");
                return;
            }
            if ($isLast && $max !== null) {
                $fail('Tier terakhir harus tanpa batas atas (tak terbatas).');
                return;
            }
            if (! $isLast && ($max === null || $max <= $min)) {
                $fail("Batas atas tier {$n} harus lebih besar dari batas bawahnya.");
                return;
            }
            $prevMax = $max;
        }
    }

    protected function attributes(array $data): array
    {
        $attrs = Arr::except($data, ['tiers']);
        $attrs['base_fee'] = $attrs['base_fee'] ?? 0;

        return $attrs;
    }

    protected function afterSave(Model $record, array $data): void
    {
        $record->tiers()->delete();

        foreach (array_values($data['tiers']) as $i => $tier) {
            $record->tiers()->create([...$tier, 'sort_order' => $i + 1, 'max_value' => $tier['max_value'] ?? null]);
        }
    }

    /** Simulasi hitung: ?usage=25&category=general (opsional ?date=) */
    public function simulate(Request $request, TariffResolver $resolver, WaterBillCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'usage'    => ['required', 'numeric', 'min:0', 'max:100000'],
            'category' => ['required', Rule::enum(WaterTariffCategory::class)],
            'date'     => ['nullable', 'date'],
        ]);

        $schedule = $resolver->waterSchedule(isset($data['date']) ? now()->parse($data['date']) : null);
        if (! $schedule) {
            return response()->json(['message' => 'Belum ada tarif air yang berlaku.'], 422);
        }

        $result = $calculator->calculate($schedule, (float) $data['usage'], WaterTariffCategory::from($data['category']));

        return response()->json(['data' => [
            'schedule_id' => $schedule->id,
            'usage'       => (float) $data['usage'],
            'category'    => $data['category'],
            ...$result,
        ]]);
    }
}