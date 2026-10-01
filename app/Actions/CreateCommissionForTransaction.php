<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SaleType;
use App\Enums\TransactionStatus;
use App\Models\Commission;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\CommissionCalculator;

final class CreateCommissionForTransaction
{
    public function __construct(private readonly CommissionCalculator $calculator) {}

    /**
     * Membuat komisi `pending` untuk transaksi yang berhak, atau null bila tidak berhak.
     * Idempoten: memanggil berulang untuk transaksi yang sama mengembalikan komisi yang sama.
     */
    public function handle(Transaction $transaction): ?Commission
    {
        if (! $this->isEligible($transaction)) {
            return null;
        }

        $rate = (string) Setting::get('default_commission_rate', '10');
        $delayDays = (int) Setting::get('approval_delay_days', '14');
        $baseAmount = (int) $transaction->base_amount; // harga kotor sebelum PPN, tanpa dikurangi diskon

        // Satu INSERT atomik; unique(transaction_id) + createOrFirst = aman saat dua job berjalan bersamaan.
        return Commission::createOrFirst(
            ['transaction_id' => $transaction->id],
            [
                'affiliate_id' => $transaction->affiliate_id,
                'base_amount' => $baseAmount,
                'rate' => $rate, // snapshot: perubahan rate nanti tidak memengaruhi komisi ini
                'amount' => $this->calculator->amount($baseAmount, $rate),
                'approvable_at' => $transaction->paid_at->copy()->addDays($delayDays),
            ],
        )->refresh();
    }

    private function isEligible(Transaction $transaction): bool
    {
        // Hanya penjualan awal; perpanjangan (renewal) dan plan_change tidak berkomisi.
        if ($transaction->sale_type !== SaleType::NewSale) {
            return false;
        }

        if ($transaction->status !== TransactionStatus::Paid) {
            return false;
        }

        $affiliate = $transaction->affiliate;

        if ($affiliate === null || ! $affiliate->isActive()) {
            return false;
        }

        // Self-referral: affiliator tidak boleh berkomisi dari pembelian sendiri.
        return strcasecmp($transaction->customer_email, $affiliate->user->email) !== 0;
    }
}
