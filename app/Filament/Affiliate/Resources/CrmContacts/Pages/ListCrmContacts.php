<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\CrmContacts\Pages;

use App\Filament\Affiliate\Resources\CrmContacts\CrmContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCrmContacts extends ListRecords
{
    protected static string $resource = CrmContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
