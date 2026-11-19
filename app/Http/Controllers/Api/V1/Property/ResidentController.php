<?php

namespace App\Http\Controllers\Api\V1\Property;

use App\Actions\Property\SaveResidentAction;
use App\Actions\Property\UpdateResidentPhotoAction;
use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\ResidentRequest;
use App\Http\Resources\Property\ResidentResource;
use App\Models\Resident;
use App\Services\Shared\RegionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ResidentController extends Controller
{
    public function __construct(private RegionService $regions) {}

    public function index(Request $request)
    {
        $request->validate([
            'search'       => ['nullable', 'string', 'max:100'],
            'is_active'    => ['nullable', 'boolean'],
            'gender'       => ['nullable', Rule::enum(Gender::class)],
            'village_code' => ['nullable', 'string', 'max:10'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $residents = Resident::query()
            ->search($request->input('search'))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->input('gender')))
            ->when($request->filled('village_code'), fn ($q) => $q->where('village_code', $request->input('village_code')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return ResidentResource::collection($residents);
    }

    public function show(int $id): ResidentResource
    {
        return $this->present(Resident::findOrFail($id));
    }

    public function store(ResidentRequest $request, SaveResidentAction $action): JsonResponse
    {
        $resident = $action->execute($request->validated());

        return $this->present($resident)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(ResidentRequest $request, int $id, SaveResidentAction $action): ResidentResource
    {
        return $this->present($action->execute($request->validated(), Resident::findOrFail($id)));
    }

    public function setActive(Request $request, int $id): ResidentResource
    {
        $data     = $request->validate(['is_active' => ['required', 'boolean']]);
        $resident = Resident::findOrFail($id);
        $resident->update($data);

        return $this->present($resident->refresh());
    }

    /** NIK lengkap. Setiap akses dicatat. */
    public function revealNik(Request $request, int $id): JsonResponse
    {
        $resident = Resident::findOrFail($id);

        // Sementara ke log. Dipindah ke audit log (activity_log) di tahap Sistem
        Log::info('NIK warga dilihat', ['resident_id' => $resident->id, 'user_id' => $request->user()->id]);

        return response()->json(['data' => ['nik' => $resident->nik]]);
    }

    public function photo(int $id)
    {
        $resident = Resident::findOrFail($id);
        $disk     = Storage::disk(config('vib.private_disk'));

        abort_unless($resident->photo_path && $disk->exists($resident->photo_path), 404);

        return $disk->response($resident->photo_path);
    }

    public function storePhoto(Request $request, int $id, UpdateResidentPhotoAction $action): ResidentResource
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048']]);

        return $this->present($action->store(Resident::findOrFail($id), $request->file('photo')));
    }

    public function destroyPhoto(int $id, UpdateResidentPhotoAction $action): ResidentResource
    {
        return $this->present($action->remove(Resident::findOrFail($id)));
    }

    private function present(Resident $resident): ResidentResource
    {
        return (new ResidentResource($resident))
            ->withRegion($this->regions->resolveVillage($resident->village_code));
    }
}
