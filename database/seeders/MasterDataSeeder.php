<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Bank, Block, ChartOfAccount, Street, Village, WorkArea, Zone};

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $sets = [
            Block::class => [
                'A' => 'Blok A', 'B' => 'Blok B', 'C' => 'Blok C', 'D' => 'Blok D', 'E' => 'Blok E', 'F' => 'Blok F',
                'G' => 'Blok G', 'H' => 'Blok H', 'I' => 'Blok I', 'J' => 'Blok J', 'K' => 'Blok K', 'L' => 'Blok L',
                'M' => 'Blok M', 'N' => 'Blok N',
            ],
            Street::class => [
                'MLT' => 'Jl. Melati', 'MWR' => 'Jl. Mawar',
                'AGR' => 'Jl. Anggrek', 'KNG' => 'Jl. Kenanga',
            ],
            Zone::class => ['Z1' => 'Zona 1', 'Z2' => 'Zona 2', 'Z3' => 'Zona 3'],
            Village::class => ['KEL-01' => 'Kelurahan Contoh'],
            WorkArea::class => [
                'GERBANG' => 'Area Gerbang', 'TAMAN' => 'Taman',
                'JALAN' => 'Jalan Lingkungan', 'DRAINASE' => 'Drainase',
            ],
            Bank::class => [
                'BCA' => 'Bank BCA', 'MANDIRI' => 'Bank Mandiri',
                'BNI' => 'Bank BNI', 'BRI' => 'Bank BRI',
            ],
            // CONTOH SAJA. Harus diganti COA asli dari aplikasi laporan keuangan
            ChartOfAccount::class => [
                '4-1001' => 'Pendapatan IPL (contoh)',
                '4-1002' => 'Pendapatan Air (contoh)',
                '4-1003' => 'Pendapatan Denda (contoh)',
            ],
        ];

        foreach ($sets as $model => $rows) {
            foreach ($rows as $code => $name) {
                $model::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
            }
        }
    }
}
