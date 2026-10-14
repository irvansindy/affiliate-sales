<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminDashboardMetrics;
use Filament\Widgets\Widget;

class TopAffiliates extends Widget
{
    protected string $view = 'filament.admin.widgets.top-affiliates';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    protected function getViewData(): array
    {
        return ['affiliates' => app(AdminDashboardMetrics::class)->topAffiliates()];
    }
}
