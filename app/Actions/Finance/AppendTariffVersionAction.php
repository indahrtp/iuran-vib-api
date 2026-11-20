<?php

namespace App\Actions\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppendTariffVersionAction
{
    /**
     * Menambah versi tarif baru ke ujung rantai dan menutup versi sebelumnya.
     * $after dipanggil di dalam transaksi (untuk menyimpan tier air).
     *
     * @param class-string<Model> $modelClass
     */
    public function execute(string $modelClass, array $data, ?callable $after = null): Model
    {
        return DB::transaction(function () use ($modelClass, $data, $after) {
            $newFrom = Carbon::parse($data['valid_from'])->startOfDay();

            $latest = $modelClass::orderByDesc('valid_from')->orderByDesc('id')->lockForUpdate()->first();

            if ($latest) {
                if ($newFrom->lte($latest->valid_from)) {
                    throw ValidationException::withMessages([
                        'valid_from' => ['Tanggal berlaku harus setelah '.$latest->valid_from->format('d-m-Y').'.'],
                    ]);
                }

                if ($latest->valid_until === null || $latest->valid_until->gte($newFrom)) {
                    $latest->update(['valid_until' => $newFrom->copy()->subDay()->toDateString()]);
                }
            }

            $record = $modelClass::create([...$data, 'valid_until' => null]);

            if ($after) {
                $after($record);
            }

            return $record->refresh();
        });
    }
}