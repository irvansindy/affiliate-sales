<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\Payouts\Pages;

use App\Filament\Affiliate\Resources\Payouts\PayoutResource;
use Filament\Resources\Pages\ListRecords;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
