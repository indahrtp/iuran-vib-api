<?php

namespace App\Enums;

enum VehicleType: string
{
    case Motorcycle = 'motorcycle';
    case Car = 'car';

    public function label(): string
    {
        return $this === self::Motorcycle ? 'Sepeda Motor' : 'Mobil';
    }
}