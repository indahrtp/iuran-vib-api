<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Services\Shared\RegionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegionController extends Controller
{
    public function provinces(): JsonResponse
    {
        return $this->list('provinces');
    }

    public function cities(Request $request): JsonResponse
    {
        $request->validate(['province_code' => ['required', 'string', 'max:2']]);

        return $this->list('cities', 'province_code', $request->input('province_code'));
    }

    public function districts(Request $request): JsonResponse
    {
        $request->validate(['city_code' => ['required', 'string', 'max:4']]);

        return $this->list('districts', 'city_code', $request->input('city_code'));
    }

    public function villages(Request $request): JsonResponse
    {
        $request->validate(['district_code' => ['required', 'string', 'max:6']]);

        return $this->list('villages', 'district_code', $request->input('district_code'));
    }

    private function list(string $table, ?string $parentColumn = null, ?string $parentCode = null): JsonResponse
    {
        $rows = DB::table(RegionService::table($table))
            ->when($parentColumn, fn ($q) => $q->where($parentColumn, $parentCode))
            ->orderBy('name')
            ->get(['code', 'name']);

        return response()->json(['data' => $rows]);
    }
}