<?php

namespace App\Enums;

enum RentalType: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Harian',
            self::Monthly => 'Bulanan',
            self::Yearly => 'Tahunan',
        };
    }
}