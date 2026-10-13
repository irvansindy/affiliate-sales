<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Tables;

use App\Enums\PurchaseType;
use App\Enums\SaleType;
use App\Services\DisplayFormatter;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('external_ref')
                    ->label(__('transactions.external_ref'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label(__('transactions.product'))
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label(__('transactions.customer_name'))
                    ->searchable(),
                TextColumn::make('affiliate.user.name')
                    ->label(__('transactions.affiliate'))
                    ->placeholder('—')
                    ->visibleFrom('lg'),
                TextColumn::make('purchase_type')
                    ->label(__('transactions.purchase_type'))
                    ->badge()
                    ->formatStateUsing(fn (PurchaseType $state): string => __('transactions.purchase_types.'.$state->value)),
                TextColumn::make('sale_type')
                    ->label(__('transactions.sale_type'))
                    ->formatStateUsing(fn (SaleType $state): string => __('transactions.sale_types.'.$state->value))
                    ->visibleFrom('lg'),
                TextColumn::make('total_paid')
                    ->label(__('transactions.total_paid'))
                    ->formatStateUsing(fn ($state): string => DisplayFormatter::rupiah((int) $state))
                    ->sortable(),
                TextColumn::make('needs_review')
                    ->label(__('transactions.review_status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('transactions.statuses.review')
                        : __('transactions.statuses.clear'))
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                TextColumn::make('paid_at')
                    ->label(__('transactions.paid_at'))
                    ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state))
                    ->sortable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('purchase_type')
                    ->label(__('transactions.purchase_type'))
                    ->options([
                        PurchaseType::Subscription->value => __('transactions.purchase_types.subscription'),
                        PurchaseType::OneTime->value => __('transactions.purchase_types.one_time'),
                    ]),
                SelectFilter::make('needs_review')
                    ->label(__('transactions.review_status'))
                    ->options([
                        '1' => __('transactions.statuses.review'),
                        '0' => __('transactions.statuses.clear'),
                    ]),
            ])
            ->defaultSort('paid_at', 'desc')
            ->emptyStateHeading(__('transactions.empty_heading'))
            ->emptyStateDescription(__('transactions.empty_description'))
            ->recordActions([
                ViewAction::make()->label(__('transactions.actions.view')),
            ]);
    }
}
