<?php

namespace App\Enums;

enum WaterTariffCategory: string
{
    case General = 'general';
    case Commercial = 'commercial';

    public function label(): string
    {
        return $this === self::General ? 'Umum' : 'Komersial';
    }
}