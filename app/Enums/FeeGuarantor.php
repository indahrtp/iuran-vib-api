<?php

namespace App\Enums;

enum FeeGuarantor: string
{
    case Owner = 'owner';
    case Occupant = 'occupant';

    public function label(): string
    {
        return $this === self::Owner ? 'Pemilik' : 'Penghuni';
    }
}