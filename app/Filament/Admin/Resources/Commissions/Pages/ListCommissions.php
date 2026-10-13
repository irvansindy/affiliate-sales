<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Commissions\Pages;

use App\Filament\Admin\Resources\Commissions\CommissionResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissions extends ListRecords
{
    protected static string $resource = CommissionResource::class;
}
