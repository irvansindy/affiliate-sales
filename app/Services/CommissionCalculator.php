<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Satu-satunya tempat perhitungan nominal komisi. Murni (tanpa database).
 */
final class CommissionCalculator
{
    /**
     * Komisi dalam rupiah, dibulatkan half-up (…,5 naik).
     *
     * $baseAmount = harga kotor produk sebelum PPN (integer rupiah).
     * $rate       = persen, mis. '10' atau '12.50'.
     *
     * Memakai aritmetika integer (basis point) agar bebas error pembulatan float.
     */
    public function amount(int $baseAmount, string|int|float $rate): int
    {
        $percent = (float) $rate;

        if ($baseAmount < 0 || $percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('Base amount harus >= 0 dan rate harus antara 0 dan 100.');
        }

        $basisPoints = (int) round($percent * 100); // 10% -> 1000

        return intdiv($baseAmount * $basisPoints + 5000, 10000);
    }
}
