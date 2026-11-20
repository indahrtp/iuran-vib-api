<?php

namespace App\Enums;

enum MeterReplaceReason: string
{
    case Broken = 'broken';
    case CalibrationExpired = 'calibration_expired';
    case Rollover = 'rollover';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Broken => 'Rusak',
            self::CalibrationExpired => 'Habis masa kalibrasi',
            self::Rollover => 'Berputar kembali ke 0',
            self::Other => 'Lainnya',
        };
    }
}