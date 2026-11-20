<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Resources\TariffResource;
use App\Models\PenaltySetting;
use Illuminate\Http\Request;

class PenaltySettingController extends VersionedTariffController
{
    protected string $model = PenaltySetting::class;

    protected function rules(): array
    {
        return [
            'due_day'      => ['required', 'integer', 'between:1,28'],
            'grace_days'   => ['required', 'integer', 'between:0,60'],
            'rate_percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            'max_amount'   => ['nullable', 'numeric', 'gt:0', 'max:9999999999'],
            'coa_code'     => $this->coaRule(),
            'valid_from'   => ['required', 'date'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }

    /** Saklar aktif/nonaktif. Boleh dipakai walau pengaturan sudah berlaku */
    public function setActive(Request $request, int $id): TariffResource
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $setting = PenaltySetting::findOrFail($id);
        $setting->update($data);

        // Sementara ke log. Dipindah ke audit log (activity_log) di tahap Sistem
        logger()->info('Pengaturan denda diubah', [
            'id' => $id, 'is_active' => $data['is_active'], 'user_id' => $request->user()->id,
        ]);

        return new TariffResource($setting->refresh());
    }
}