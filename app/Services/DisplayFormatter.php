<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DisplayFormatter
{
    public static function rupiah(?int $amount): string
    {
        if ($amount === null) {
            return '—';
        }

        return 'Rp '.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', (string) $amount);
    }

    public static function dateTime(CarbonInterface|string|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        return Carbon::parse($value)->timezone('Asia/Jakarta')->locale('id')
            ->translatedFormat('j M Y, H:i').' WIB';
    }

    public static function bankAccount(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return '****'.(mb_strlen($value) > 4 ? mb_substr($value, -4) : '');
    }
}
