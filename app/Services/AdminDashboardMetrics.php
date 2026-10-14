<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommissionStatus;
use App\Enums\TransactionStatus;
use App\Models\Affiliate;
use App\Models\Commission;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class AdminDashboardMetrics
{
    /** @return array{sales_count: int, sales_total: int, approved_commissions: int, paid_commissions: int} */
    public function summary(): array
    {
        $sales = Transaction::query()->where('status', TransactionStatus::Paid)
            ->selectRaw('COUNT(*) AS sales_count, COALESCE(SUM(total_paid), 0) AS sales_total')->first();
        $commissions = Commission::query()->whereIn('status', [CommissionStatus::Approved, CommissionStatus::Paid])
            ->select('status')->selectRaw('SUM(amount) AS total')->groupBy('status')->pluck('total', 'status');

        return [
            'sales_count' => (int) $sales->sales_count,
            'sales_total' => (int) $sales->sales_total,
            'approved_commissions' => (int) ($commissions[CommissionStatus::Approved->value] ?? 0),
            'paid_commissions' => (int) ($commissions[CommissionStatus::Paid->value] ?? 0),
        ];
    }

    /** @return list<array{name: string, total_paid: int, sales_count: int}> */
    public function topAffiliates(): array
    {
        $sales = Transaction::query()->where('status', TransactionStatus::Paid)->whereNotNull('affiliate_id')
            ->select('affiliate_id')->selectRaw('SUM(total_paid) AS total_paid, COUNT(*) AS sales_count')
            ->groupBy('affiliate_id')->orderByDesc('total_paid')->limit(5)->get();
        $affiliates = Affiliate::query()->with('user')->whereIn('id', $sales->pluck('affiliate_id'))->get()->keyBy('id');

        return $sales->map(fn (Transaction $sale): array => [
            'name' => $affiliates[$sale->affiliate_id]?->user?->name ?? __('admin_reports.unknown_affiliate'),
            'total_paid' => (int) $sale->total_paid,
            'sales_count' => (int) $sale->sales_count,
        ])->all();
    }

    /** @return list<array{month: string, total_paid: int}> */
    public function trend(): array
    {
        $start = now()->startOfMonth()->subMonths(5);
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', paid_at)"
            : "DATE_FORMAT(paid_at, '%Y-%m')";
        $amounts = Transaction::query()->where('status', TransactionStatus::Paid)
            ->where('paid_at', '>=', $start)
            ->selectRaw("{$monthExpression} AS month, SUM(total_paid) AS total_paid")
            ->groupByRaw($monthExpression)->pluck('total_paid', 'month');

        $trend = [];
        for ($index = 0; $index < 6; $index++) {
            $month = $start->copy()->addMonths($index)->format('Y-m');
            $trend[] = ['month' => $month, 'total_paid' => (int) ($amounts[$month] ?? 0)];
        }

        return $trend;
    }
}
