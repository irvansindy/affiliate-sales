<?php

namespace App\Filament\Affiliate\Resources\SalesSubmissions\Pages;

use App\Filament\Affiliate\Resources\SalesSubmissions\SalesSubmissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesSubmissions extends ListRecords
{
    protected static string $resource = SalesSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
