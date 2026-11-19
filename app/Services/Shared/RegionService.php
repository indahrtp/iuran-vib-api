<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;

class RegionService
{
    public static function table(string $name): string
    {
        return config('laravolt.indonesia.table_prefix', 'indonesia_').$name;
    }

    /** Dari kode kelurahan, kembalikan seluruh jenjang wilayahnya. Null jika tidak ada. */
    public function resolveVillage(string $villageCode): ?array
    {
        $row = DB::table(self::table('villages').' as v')
            ->join(self::table('districts').' as d', 'd.code', '=', 'v.district_code')
            ->join(self::table('cities').' as c', 'c.code', '=', 'd.city_code')
            ->join(self::table('provinces').' as p', 'p.code', '=', 'c.province_code')
            ->where('v.code', $villageCode)
            ->select([
                'v.code as village_code', 'v.name as village_name',
                'd.code as district_code', 'd.name as district_name',
                'c.code as city_code', 'c.name as city_name',
                'p.code as province_code', 'p.name as province_name',
            ])
            ->first();

        return $row ? (array) $row : null;
    }
}