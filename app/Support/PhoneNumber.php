<?php

namespace App\Support;

class PhoneNumber
{
    /** Ubah 0812-3456-7890, +62 812..., 812... menjadi 6281234567890 */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }
}