<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\Commissions\Pages;

use App\Filament\Affiliate\Resources\Commissions\CommissionResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissions extends ListRecords
{
    protected static string $resource = CommissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
