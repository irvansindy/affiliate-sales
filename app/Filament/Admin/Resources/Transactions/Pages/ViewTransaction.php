<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Pages;

use App\Filament\Admin\Resources\Transactions\TransactionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;
}
