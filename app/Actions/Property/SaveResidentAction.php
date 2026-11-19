<?php

namespace App\Actions\Property;

use App\Models\Resident;
use App\Services\Shared\RegionService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaveResidentAction
{
    public function __construct(private RegionService $regions) {}

    public function execute(array $data, ?Resident $resident = null): Resident
    {
        $region = $this->regions->resolveVillage($data['village_code'])
            ?? throw new InvalidArgumentException('Kelurahan tidak ditemukan.');

        $payload = [
            ...collect($data)->except('village_code')->all(),
            'village_code'  => $region['village_code'],
            'district_code' => $region['district_code'],
            'city_code'     => $region['city_code'],
            'province_code' => $region['province_code'],
        ];

        return DB::transaction(function () use ($resident, $payload) {
            if ($resident) {
                $resident->update($payload);

                return $resident->refresh();
            }

            return Resident::create($payload)->refresh();
        });
    }
}