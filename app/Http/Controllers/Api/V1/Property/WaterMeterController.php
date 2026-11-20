<?php

namespace App\Http\Controllers\Api\V1\Property;

use App\Actions\Property\ManageWaterMeterAction;
use App\Enums\MeterReplaceReason;
use App\Http\Controllers\Controller;
use App\Http\Resources\Property\WaterMeterResource;
use App\Models\Unit;
use App\Models\WaterMeter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class WaterMeterController extends Controller
{
    public function index(int $unitId)
    {
        Unit::findOrFail($unitId);

        return WaterMeterResource::collection(
            WaterMeter::where('unit_id', $unitId)->orderByDesc('installed_at')->orderByDesc('id')->get()
        );
    }

    public function store(Request $request, int $unitId, ManageWaterMeterAction $action): JsonResponse
    {
        $data = $request->validate([
            'meter_number'  => ['required', 'string', 'max:50', 'unique:water_meters,meter_number'],
            'initial_value' => ['required', 'numeric', 'min:0'],
            'installed_at'  => ['required', 'date', 'before_or_equal:today'],
        ], $this->messages());

        $meter = $action->install(Unit::findOrFail($unitId), $data, $request->user()->id);

        return (new WaterMeterResource($meter))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function replace(Request $request, int $unitId, ManageWaterMeterAction $action): JsonResponse
    {
        $data = $request->validate([
            'old_final_value' => ['required', 'numeric', 'min:0'],
            'meter_number'    => ['required', 'string', 'max:50', 'unique:water_meters,meter_number'],
            'initial_value'   => ['required', 'numeric', 'min:0'],
            'replaced_at'     => ['required', 'date', 'before_or_equal:today'],
            'replace_reason'  => ['required', Rule::enum(MeterReplaceReason::class)],
        ], $this->messages());

        $meter = $action->replace(Unit::findOrFail($unitId), $data, $request->user()->id);

        return (new WaterMeterResource($meter))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    private function messages(): array
    {
        return [
            'meter_number.unique'   => 'Nomor meter sudah dipakai.',
            'meter_number.required' => 'Nomor meter wajib diisi.',
        ];
    }
}