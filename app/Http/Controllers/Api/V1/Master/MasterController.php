<?php

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use App\Http\Resources\MasterResource;
use App\Models\MasterModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

abstract class MasterController extends Controller
{
    /** @var class-string<MasterModel> */
    protected string $model;

    public function index(Request $request)
    {
        $request->validate([
            'search'    => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'all'       => ['nullable', 'boolean'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $this->model::query()
            ->search($request->input('search'))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('code');

        // ?all=1 untuk isi dropdown di frontend
        if ($request->boolean('all')) {
            return MasterResource::collection($query->get());
        }

        return MasterResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function show(int $id): MasterResource
    {
        return new MasterResource($this->model::findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $record = $this->model::create($request->validate($this->rules()))->refresh();

        return (new MasterResource($record))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): MasterResource
    {
        $record = $this->model::findOrFail($id);
        $record->update($request->validate($this->rules($id)));

        return new MasterResource($record->refresh());
    }

    public function destroy(int $id): JsonResponse|Response
    {
        $record = $this->model::findOrFail($id);

        try {
            $record->delete();
        } catch (QueryException $e) {
            // Kode 23000 = pelanggaran foreign key (data sudah dipakai tabel lain)
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Data sudah dipakai sehingga tidak dapat dihapus. Nonaktifkan saja.',
                ], 409);
            }
            throw $e;
        }

        return response()->noContent();
    }

    protected function rules(?int $ignoreId = null): array
    {
        $table = (new $this->model)->getTable();

        return [
            'code'      => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._\-]+$/',
                            Rule::unique($table, 'code')->ignore($ignoreId)],
            'name'      => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}