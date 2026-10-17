<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\CrmContacts\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CrmContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('crm.name'))->searchable()->sortable(),
            TextColumn::make('email')->label(__('crm.email'))->searchable()->visibleFrom('md'),
            TextColumn::make('phone')->label(__('crm.phone'))->searchable()->visibleFrom('lg'),
            TextColumn::make('company')->label(__('crm.company'))->searchable()->visibleFrom('lg'),
        ])
            ->defaultSort('name')->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('crm.empty_contacts'))
            ->emptyStateDescription(__('crm.empty_contacts_description'))
            ->recordActions([EditAction::make()]);
    }
}
