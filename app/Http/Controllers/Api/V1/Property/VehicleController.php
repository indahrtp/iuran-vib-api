<?php

namespace App\Http\Controllers\Api\V1\Property;

use App\Http\Controllers\Controller;
use App\Http\Requests\Property\VehicleRequest;
use App\Http\Resources\Property\VehicleResource;
use App\Models\Unit;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VehicleController extends Controller
{
    public function index(Request $request, int $unitId)
    {
        Unit::findOrFail($unitId);

        $vehicles = Vehicle::with('resident')->where('unit_id', $unitId)
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('plate_number')->get();

        return VehicleResource::collection($vehicles);
    }

    public function store(VehicleRequest $request, int $unitId): JsonResponse
    {
        Unit::findOrFail($unitId);

        $vehicle = Vehicle::create([...$request->validated(), 'unit_id' => $unitId])->refresh()->load('resident');

        return (new VehicleResource($vehicle))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(VehicleRequest $request, int $id): VehicleResource
    {
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->update($request->validated());

        return new VehicleResource($vehicle->refresh()->load('resident'));
    }

    public function setActive(Request $request, int $id): VehicleResource
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->update($data);

        return new VehicleResource($vehicle->refresh()->load('resident'));
    }
}