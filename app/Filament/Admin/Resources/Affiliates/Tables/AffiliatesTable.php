<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Affiliates\Tables;

use App\Enums\AffiliateStatus;
use App\Filament\Admin\Resources\Affiliates\AffiliateStatusActions;
use App\Services\DisplayFormatter;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label(__('affiliates.name'))->searchable()->sortable(),
                TextColumn::make('user.email')->label(__('affiliates.email'))->searchable()->visibleFrom('md'),
                TextColumn::make('status')->label(__('affiliates.status'))->badge()
                    ->formatStateUsing(fn (AffiliateStatus $state): string => __('affiliates.statuses.'.$state->value))
                    ->color(fn (AffiliateStatus $state): string => match ($state) {
                        AffiliateStatus::Pending => 'warning',
                        AffiliateStatus::Active => 'success',
                        AffiliateStatus::Suspended, AffiliateStatus::Rejected => 'danger',
                    }),
                TextColumn::make('referral_code')->label(__('affiliates.referral_code'))->searchable()->visibleFrom('lg'),
                TextColumn::make('approved_at')->label(__('affiliates.approved_at'))
                    ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state))
                    ->placeholder('—')->sortable()->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('affiliates.status'))->options(
                    collect(AffiliateStatus::cases())->mapWithKeys(
                        fn (AffiliateStatus $status): array => [$status->value => __('affiliates.statuses.'.$status->value)]
                    )->all()
                ),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('affiliates.empty_heading'))
            ->emptyStateDescription(__('affiliates.empty_description'))
            ->recordActions([
                ViewAction::make()->label(__('affiliates.view')),
                ...AffiliateStatusActions::make(),
            ]);
    }
}
