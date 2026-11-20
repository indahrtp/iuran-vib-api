<?php

namespace App\Enums;

enum BuildingStatus: string
{
    case Occupied = 'occupied';
    case Rented = 'rented';
    case VacationHome = 'vacation_home';
    case Empty = 'empty';

    public function label(): string
    {
        return match ($this) {
            self::Occupied => 'Dihuni',
            self::Rented => 'Disewakan',
            self::VacationHome => 'Rumah Istirahat',
            self::Empty => 'Kosong',
        };
    }
}