<?php

namespace App\Enums;

enum ChargeType: string
{
    case Flat = 'flat';
    case PerM3 = 'per_m3';

    public function label(): string
    {
        return $this === self::Flat ? 'Tetap (flat)' : 'Per m³';
    }
}