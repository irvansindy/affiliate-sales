<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\Commissions\Pages;

use App\Filament\Affiliate\Resources\Commissions\CommissionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCommission extends ViewRecord
{
    protected static string $resource = CommissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
