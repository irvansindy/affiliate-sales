<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminDashboardMetrics;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SalesTrendChart extends ChartWidget
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $this->heading = __('admin_reports.trend');
        parent::mount();
    }

    protected function getData(): array
    {
        $trend = app(AdminDashboardMetrics::class)->trend();

        return [
            'datasets' => [[
                'label' => __('admin_reports.sales_total'),
                'data' => array_column($trend, 'total_paid'),
            ]],
            'labels' => array_map(
                fn (array $month): string => Carbon::createFromFormat('!Y-m', $month['month'])->locale('id')->translatedFormat('M Y'),
                $trend,
            ),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
