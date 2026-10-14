<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\Transactions\Pages;

use App\Filament\Affiliate\Resources\Transactions\TransactionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
