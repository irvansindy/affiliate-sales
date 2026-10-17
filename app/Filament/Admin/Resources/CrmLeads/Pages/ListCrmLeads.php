<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CrmLeads\Pages;

use App\Filament\Admin\Resources\CrmLeads\CrmLeadResource;
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
