<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\CrmLeads\Pages;

use App\Filament\Affiliate\Resources\CrmLeads\CrmLeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCrmLeads extends ListRecords
{
    protected static string $resource = CrmLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
