<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommissionStatus;
use App\Enums\TransactionStatus;
use App\Models\Affiliate;
use App\Models\Commission;
use App\Models\ReferralClick;
use App\Models\Transaction;

final class AffiliateDashboardMetrics
{
    /** @return array{clicks: int, sales: int, pending: int, approved: int, paid: int} */
    public function for(Affiliate $affiliate): array
    {
        $totals = Commission::query()->where('affiliate_id', $affiliate->getKey())
            ->select('status')->selectRaw('SUM(amount) AS total')->groupBy('status')
            ->pluck('total', 'status');

        return [
            'clicks' => ReferralClick::query()->where('affiliate_id', $affiliate->getKey())->count(),
            'sales' => Transaction::query()->where('affiliate_id', $affiliate->getKey())
                ->where('status', TransactionStatus::Paid)->count(),
            'pending' => (int) ($totals[CommissionStatus::Pending->value] ?? 0),
            'approved' => (int) ($totals[CommissionStatus::Approved->value] ?? 0),
            'paid' => (int) ($totals[CommissionStatus::Paid->value] ?? 0),
        ];
    }
}
