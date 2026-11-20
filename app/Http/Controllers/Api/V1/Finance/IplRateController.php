<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Models\IplRate;
use App\Models\Unit;
use App\Services\Finance\IplCalculator;
use App\Services\Finance\TariffResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IplRateController extends VersionedTariffController
{
    protected string $model = IplRate::class;

    protected function rules(): array
    {
        return [
            'rate_per_m2' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'coa_code'    => $this->coaRule(),
            'valid_from'  => ['required', 'date'],
        ];
    }

    /** Simulasi: ?land_area=120 atau ?unit_id=5 (opsional ?date=) */
    public function simulate(Request $request, TariffResolver $resolver, IplCalculator $calculator): JsonResponse
    {
        $request->validate([
            'land_area' => ['required_without:unit_id', 'nullable', 'numeric', 'gt:0'],
            'unit_id'   => ['required_without:land_area', 'nullable', 'integer', 'exists:units,id'],
            'date'      => ['nullable', 'date'],
        ]);

        $rate = $resolver->iplRate($request->filled('date') ? now()->parse($request->input('date')) : null);
        if (! $rate) {
            return response()->json(['message' => 'Belum ada tarif IPL yang berlaku.'], 422);
        }

        $area = $request->filled('unit_id')
            ? (float) Unit::findOrFail($request->integer('unit_id'))->land_area
            : (float) $request->input('land_area');

        return response()->json(['data' => [
            'rate_per_m2' => $rate->rate_per_m2,
            'land_area'   => $area,
            'amount'      => $calculator->amount($rate, $area),
        ]]);
    }
}