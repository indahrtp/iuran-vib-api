<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\AppendTariffVersionAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\TariffResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

abstract class VersionedTariffController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    /** Relasi yang dimuat bersama (tarif air: tiers) */
    protected array $with = [];

    /** Aturan validasi untuk membuat versi baru */
    abstract protected function rules(): array;

    /** Kolom yang disimpan ke tabel utama (tarif air membuang 'tiers') */
    protected function attributes(array $data): array
    {
        return $data;
    }

    /** Dipanggil setelah simpan di dalam transaksi (tarif air menyimpan tier) */
    protected function afterSave(Model $record, array $data): void {}

    protected function coaRule(): array
    {
        return ['required', 'string', 'max:30', Rule::exists('chart_of_accounts', 'code')->where('is_active', true)];
    }

    public function index(Request $request)
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return TariffResource::collection(
            $this->model::with($this->with)->orderByDesc('valid_from')->orderByDesc('id')
                ->paginate($request->integer('per_page', 15))
        );
    }

    /** Versi yang berlaku hari ini. data = null jika belum ada atau dinonaktifkan */
    public function current(): JsonResponse
    {
        $record = $this->model::with($this->with)->effectiveOn()->orderByDesc('valid_from')->first();

        return response()->json(['data' => $record ? (new TariffResource($record))->resolve() : null]);
    }

    public function show(int $id): TariffResource
    {
        return new TariffResource($this->model::with($this->with)->findOrFail($id));
    }

    public function store(Request $request, AppendTariffVersionAction $action): JsonResponse
    {
        $data = $request->validate($this->rules());

        $record = $action->execute(
            $this->model,
            $this->attributes($data),
            fn (Model $r) => $this->afterSave($r, $data),
        );

        return (new TariffResource($record->load($this->with)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): TariffResource|JsonResponse
    {
        $record = $this->model::findOrFail($id);

        if ($record->hasStarted()) {
            return response()->json([
                'message' => 'Tarif yang sudah berlaku tidak dapat diubah. Buat versi baru dengan tanggal berlaku yang baru.',
            ], 422);
        }

        // Tanggal berlaku tidak diubah. Untuk mengganti tanggal, hapus lalu buat ulang
        $data = $request->validate(Arr::except($this->rules(), ['valid_from']));

        DB::transaction(function () use ($record, $data) {
            $record->update($this->attributes($data));
            $this->afterSave($record, $data);
        });

        return new TariffResource($record->refresh()->load($this->with));
    }

    public function destroy(int $id): JsonResponse|Response
    {
        $record = $this->model::findOrFail($id);

        if ($record->hasStarted()) {
            return response()->json(['message' => 'Tarif yang sudah berlaku tidak dapat dihapus.'], 409);
        }

        $isLatest = ! $this->model::where('valid_from', '>', $record->valid_from)->exists();
        if (! $isLatest) {
            return response()->json(['message' => 'Hanya versi terbaru yang dapat dihapus.'], 409);
        }

        DB::transaction(function () use ($record) {
            // Buka kembali versi sebelumnya jika masa berlakunya ditutup oleh versi ini
            $previous = $this->model::where('valid_from', '<', $record->valid_from)
                ->orderByDesc('valid_from')->first();

            if ($previous && $previous->valid_until
                && $previous->valid_until->toDateString() === $record->valid_from->copy()->subDay()->toDateString()) {
                $previous->update(['valid_until' => null]);
            }

            $record->delete();
        });

        return response()->noContent();
    }
}