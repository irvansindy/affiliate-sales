<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CrmContacts\Tables;

use App\Models\Affiliate;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CrmContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('crm.name'))->searchable()->sortable(),
            TextColumn::make('affiliate.user.name')->label(__('crm.affiliate'))->searchable(),
            TextColumn::make('email')->label(__('crm.email'))->searchable()->visibleFrom('md'),
            TextColumn::make('phone')->label(__('crm.phone'))->searchable()->visibleFrom('lg'),
            TextColumn::make('company')->label(__('crm.company'))->searchable()->visibleFrom('lg'),
        ])
            ->filters([
                SelectFilter::make('affiliate_id')->label(__('crm.affiliate'))
                    ->options(fn (): array => Affiliate::query()->with('user')->get()->mapWithKeys(
                        fn (Affiliate $affiliate): array => [$affiliate->id => $affiliate->user->name.' ('.$affiliate->referral_code.')']
                    )->all()),
            ])
            ->defaultSort('name')->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('crm.empty_contacts'))
            ->emptyStateDescription(__('crm.empty_contacts_description'))
            ->recordActions([EditAction::make()]);
    }
}
