<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminDashboardMetrics;
use App\Services\DisplayFormatter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminSalesStats extends StatsOverviewWidget
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    protected function getStats(): array
    {
        $summary = app(AdminDashboardMetrics::class)->summary();

        return [
            Stat::make(__('admin_reports.sales_count'), (string) $summary['sales_count']),
            Stat::make(__('admin_reports.sales_total'), DisplayFormatter::rupiah($summary['sales_total'])),
            Stat::make(__('admin_reports.approved_commissions'), DisplayFormatter::rupiah($summary['approved_commissions'])),
            Stat::make(__('admin_reports.paid_commissions'), DisplayFormatter::rupiah($summary['paid_commissions'])),
        ];
    }
}
