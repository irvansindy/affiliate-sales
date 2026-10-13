<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Commissions\Tables;

use App\Enums\CommissionStatus;
use App\Services\DisplayFormatter;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction.external_ref')->label(__('commissions.transaction'))->searchable(),
                TextColumn::make('affiliate.user.name')->label(__('commissions.affiliate'))->searchable(),
                TextColumn::make('transaction.product.name')->label(__('commissions.product'))->visibleFrom('lg'),
                TextColumn::make('amount')->label(__('commissions.amount'))
                    ->formatStateUsing(fn ($state): string => DisplayFormatter::rupiah((int) $state))->sortable(),
                TextColumn::make('status')->label(__('commissions.status'))->badge()
                    ->formatStateUsing(fn (CommissionStatus $state): string => __('commissions.statuses.'.$state->value))
                    ->color(fn (CommissionStatus $state): string => match ($state) {
                        CommissionStatus::Pending => 'warning',
                        CommissionStatus::Approved => 'info',
                        CommissionStatus::Paid => 'success',
                        CommissionStatus::Rejected, CommissionStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('approvable_at')->label(__('commissions.approvable_at'))
                    ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state))->sortable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('commissions.status'))->options(
                    collect(CommissionStatus::cases())->mapWithKeys(
                        fn (CommissionStatus $status): array => [$status->value => __('commissions.statuses.'.$status->value)]
                    )->all()
                ),
            ])
            ->defaultSort('approvable_at', 'desc')
            ->emptyStateHeading(__('commissions.empty_heading'))
            ->emptyStateDescription(__('commissions.empty_description'))
            ->recordActions([
                ViewAction::make()->label(__('commissions.actions.view')),
            ]);
    }
}
