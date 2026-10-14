<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Widgets;

use App\Services\AffiliateDashboardMetrics;
use App\Services\DisplayFormatter;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AffiliateStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $metrics = app(AffiliateDashboardMetrics::class)->for(Filament::auth()->user()->affiliate);

        return [
            Stat::make(__('affiliate_panel.clicks'), number_format($metrics['clicks'], 0, ',', '.')),
            Stat::make(__('affiliate_panel.sales'), number_format($metrics['sales'], 0, ',', '.')),
            Stat::make(__('affiliate_panel.pending'), DisplayFormatter::rupiah($metrics['pending'])),
            Stat::make(__('affiliate_panel.approved'), DisplayFormatter::rupiah($metrics['approved'])),
            Stat::make(__('affiliate_panel.paid'), DisplayFormatter::rupiah($metrics['paid'])),
        ];
    }
}
