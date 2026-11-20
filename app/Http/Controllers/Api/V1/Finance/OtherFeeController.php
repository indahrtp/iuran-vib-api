<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\TariffResource;
use App\Models\OtherFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class OtherFeeController extends Controller
{
    private function coaRule(): array
    {
        return ['required', 'string', 'max:30', Rule::exists('chart_of_accounts', 'code')->where('is_active', true)];
    }

    public function index(Request $request)
    {
        $request->validate([
            'search'       => ['nullable', 'string', 'max:100'],
            'effective_on' => ['nullable', 'date'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $fees = OtherFee::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%'))
            ->when($request->filled('effective_on'), fn ($q) => $q->effectiveOn(Carbon::parse($request->input('effective_on'))))
            ->orderByDesc('valid_from')->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return TariffResource::collection($fees);
    }

    public function show(int $id): TariffResource
    {
        return new TariffResource(OtherFee::findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'amount'      => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'coa_code'    => $this->coaRule(),
            'valid_from'  => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $fee = OtherFee::create($data)->refresh();

        return (new TariffResource($fee))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): TariffResource|JsonResponse
    {
        $fee = OtherFee::findOrFail($id);

        if (! $fee->hasStarted()) {
            $data = $request->validate([
                'name'        => ['required', 'string', 'max:100'],
                'amount'      => ['required', 'numeric', 'gt:0', 'max:9999999999'],
                'coa_code'    => $this->coaRule(),
                'valid_from'  => ['required', 'date'],
                'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
                'is_active'   => ['sometimes', 'boolean'],
            ]);
        } else {
            // Sudah berlaku: nominal dan akun terkunci. Hanya nama, tanggal berakhir, dan status
            $data = $request->validate([
                'name'        => ['required', 'string', 'max:100'],
                'amount'      => ['sometimes', 'numeric'],
                'coa_code'    => ['sometimes', 'string'],
                'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
                'is_active'   => ['sometimes', 'boolean'],
            ]);

            $changed = [];
            if (isset($data['amount']) && abs((float) $data['amount'] - $fee->amount) > 0.004) {
                $changed['amount'] = ['Nominal iuran yang sudah berlaku tidak dapat diubah. Buat iuran baru.'];
            }
            if (isset($data['coa_code']) && strtoupper($data['coa_code']) !== strtoupper($fee->coa_code)) {
                $changed['coa_code'] = ['Akun COA iuran yang sudah berlaku tidak dapat diubah.'];
            }
            if ($changed) {
                return response()->json(['message' => 'Iuran yang sudah berlaku hanya dapat diakhiri atau dinonaktifkan.', 'errors' => $changed], 422);
            }
            unset($data['amount'], $data['coa_code']);
        }

        $fee->update($data);

        return new TariffResource($fee->refresh());
    }

    public function destroy(int $id): JsonResponse|Response
    {
        $fee = OtherFee::findOrFail($id);

        if ($fee->hasStarted()) {
            return response()->json(['message' => 'Iuran yang sudah berlaku tidak dapat dihapus. Akhiri atau nonaktifkan saja.'], 409);
        }

        $fee->delete();

        return response()->noContent();
    }
}