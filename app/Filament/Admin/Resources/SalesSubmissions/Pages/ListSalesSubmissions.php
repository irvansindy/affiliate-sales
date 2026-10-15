<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SalesSubmissions\Pages;

use App\Filament\Admin\Resources\SalesSubmissions\SalesSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListSalesSubmissions extends ListRecords
{
    protected static string $resource = SalesSubmissionResource::class;
}
