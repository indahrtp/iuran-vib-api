<?php

namespace App\Http\Controllers\Api\V1\Property;

use App\Actions\Property\ReplaceUnitPartyAction;
use App\Actions\Property\SaveUnitAction;
use App\Enums\BuildingStatus;
use App\Enums\FeeGuarantor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\UnitRequest;
use App\Http\Resources\Property\UnitResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UnitController extends Controller
{
    private const WITH = ['street', 'block', 'zone', 'village', 'owner', 'occupant', 'activeMeter'];

    public function index(Request $request)
    {
        $request->validate([
            'search'          => ['nullable', 'string', 'max:100'],
            'block_id'        => ['nullable', 'integer'],
            'street_id'       => ['nullable', 'integer'],
            'zone_id'         => ['nullable', 'integer'],
            'building_status' => ['nullable', Rule::enum(BuildingStatus::class)],
            'fee_guarantor'   => ['nullable', Rule::enum(FeeGuarantor::class)],
            'is_vacant_land'  => ['nullable', 'boolean'],
            'is_active'       => ['nullable', 'boolean'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $units = Unit::with(self::WITH)
            ->search($request->input('search'))
            ->when($request->filled('block_id'), fn ($q) => $q->where('block_id', $request->integer('block_id')))
            ->when($request->filled('street_id'), fn ($q) => $q->where('street_id', $request->integer('street_id')))
            ->when($request->filled('zone_id'), fn ($q) => $q->where('zone_id', $request->integer('zone_id')))
            ->when($request->filled('building_status'), fn ($q) => $q->where('building_status', $request->input('building_status')))
            ->when($request->filled('fee_guarantor'), fn ($q) => $q->where('fee_guarantor', $request->input('fee_guarantor')))
            ->when($request->has('is_vacant_land'), fn ($q) => $q->where('is_vacant_land', $request->boolean('is_vacant_land')))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('street_id')->orderBy('block_id')
            ->orderByRaw('CAST(house_number AS UNSIGNED)')->orderBy('house_number')
            ->paginate($request->integer('per_page', 15));

        return UnitResource::collection($units);
    }

    /** Angka untuk chip filter di halaman daftar unit (unit aktif saja) */
    public function summary(): JsonResponse
    {
        $row = DB::table('units')->where('is_active', true)->selectRaw("
            COUNT(*) AS total,
            COALESCE(SUM(is_vacant_land = 0 AND building_status = 'occupied'), 0) AS occupied,
            COALESCE(SUM(is_vacant_land = 0 AND building_status = 'rented'), 0) AS rented,
            COALESCE(SUM(is_vacant_land = 0 AND building_status = 'vacation_home'), 0) AS vacation_home,
            COALESCE(SUM(is_vacant_land = 0 AND building_status = 'empty'), 0) AS `empty`,
            COALESCE(SUM(is_vacant_land = 1), 0) AS vacant_land
        ")->first();

        return response()->json(['data' => array_map('intval', (array) $row)]);
    }

    public function show(int $id): UnitResource
    {
        return new UnitResource(Unit::with(self::WITH)->findOrFail($id));
    }

    public function store(UnitRequest $request, SaveUnitAction $action): JsonResponse
    {
        $unit = $action->execute($request->validated());

        return (new UnitResource($unit->load(self::WITH)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UnitRequest $request, int $id, SaveUnitAction $action): UnitResource
    {
        $unit = $action->execute($request->validated(), Unit::findOrFail($id));

        return new UnitResource($unit->load(self::WITH));
    }

    public function setActive(Request $request, int $id): UnitResource
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $unit = Unit::findOrFail($id);
        $unit->update($data);   // tahap tagihan: tolak nonaktif jika masih ada tagihan belum lunas

        return new UnitResource($unit->load(self::WITH));
    }

    public function history(int $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);

        $rows = fn ($collection) => $collection->map(fn ($r) => [
            'id'            => $r->id,
            'resident_id'   => $r->resident_id,
            'resident_name' => $r->resident->name,
            'start_date'    => $r->start_date->toDateString(),
            'end_date'      => $r->end_date?->toDateString(),
            'is_current'    => $r->end_date === null,
        ])->values();

        return response()->json(['data' => [
            'owners'    => $rows($unit->ownerships()->with('resident')->orderByDesc('start_date')->orderByDesc('id')->get()),
            'occupants' => $rows($unit->occupancies()->with('resident')->orderByDesc('start_date')->orderByDesc('id')->get()),
        ]]);
    }

    public function replaceOwner(Request $request, int $id, ReplaceUnitPartyAction $action): UnitResource
    {
        $data = $request->validate([
            'resident_id'      => ['required', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'start_date'       => ['required', 'date', 'before_or_equal:today'],
            'set_as_guarantor' => ['sometimes', 'boolean'],
        ], [
            'resident_id.required' => 'Pemilik baru wajib dipilih.',
            'start_date.required'  => 'Tanggal mulai wajib diisi.',
        ]);

        return $this->replace($id, 'owner', $data, $action);
    }

    public function replaceOccupant(Request $request, int $id, ReplaceUnitPartyAction $action): UnitResource
    {
        $data = $request->validate([
            'resident_id'      => ['nullable', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'start_date'       => ['required', 'date', 'before_or_equal:today'],
            'set_as_guarantor' => ['sometimes', 'boolean'],
        ], [
            'start_date.required' => 'Tanggal mulai wajib diisi.',
        ]);

        return $this->replace($id, 'occupant', $data, $action);
    }

    private function replace(int $id, string $party, array $data, ReplaceUnitPartyAction $action): UnitResource
    {
        $unit = $action->execute(
            Unit::findOrFail($id),
            $party,
            isset($data['resident_id']) ? (int) $data['resident_id'] : null,
            $data['start_date'],
            (bool) ($data['set_as_guarantor'] ?? false),
        );

        return new UnitResource($unit->load(self::WITH));
    }
}