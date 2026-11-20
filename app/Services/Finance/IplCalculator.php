<?php

namespace App\Services\Finance;

use App\Models\IplRate;

class IplCalculator
{
    /** IPL = tarif per m² × luas tanah. Tanah kosong tetap ditagih */
    public function amount(IplRate $rate, float $landArea): float
    {
        return round($rate->rate_per_m2 * $landArea, 2);
    }

    /** Baris rincian tagihan (item_type = ipl) */
    public function item(IplRate $rate, float $landArea): array
    {
        return [
            'item_type'   => 'ipl',
            'ref_id'      => $rate->id,
            'description' => 'IPL',
            'quantity'    => $landArea,
            'unit_price'  => $rate->rate_per_m2,
            'amount'      => $this->amount($rate, $landArea),
            'coa_code'    => $rate->coa_code,
        ];
    }
}