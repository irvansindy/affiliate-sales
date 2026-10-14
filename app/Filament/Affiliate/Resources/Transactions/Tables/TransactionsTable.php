<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\Transactions\Tables;

use App\Enums\PurchaseType;
use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Services\DisplayFormatter;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('external_ref')->label(__('transactions.external_ref'))->searchable()->sortable(),
                TextColumn::make('product.name')->label(__('transactions.product'))->searchable(),
                TextColumn::make('customer_name')->label(__('transactions.customer_name'))->searchable()->visibleFrom('md'),
                TextColumn::make('purchase_type')->label(__('transactions.purchase_type'))
                    ->formatStateUsing(fn (PurchaseType $state): string => __('transactions.purchase_types.'.$state->value)),
                TextColumn::make('total_paid')->label(__('transactions.total_paid'))
                    ->formatStateUsing(fn ($state): string => DisplayFormatter::rupiah((int) $state))->sortable(),
                TextColumn::make('status')->label(__('transactions.status'))->badge()
                    ->formatStateUsing(fn (TransactionStatus $state): string => __('transactions.statuses.'.$state->value)),
                TextColumn::make('paid_at')->label(__('transactions.paid_at'))
                    ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state))->sortable()->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('product_id')->label(__('transactions.product'))
                    ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('purchase_type')->label(__('transactions.purchase_type'))
                    ->options(collect(PurchaseType::cases())->mapWithKeys(fn (PurchaseType $type): array => [
                        $type->value => __('transactions.purchase_types.'.$type->value),
                    ])->all()),
                SelectFilter::make('status')->label(__('transactions.status'))
                    ->options(collect(TransactionStatus::cases())->mapWithKeys(fn (TransactionStatus $status): array => [
                        $status->value => __('transactions.statuses.'.$status->value),
                    ])->all()),
                Filter::make('paid_at')->label(__('affiliate_panel.date_range'))->schema([
                    DatePicker::make('from')->label(__('affiliate_panel.from')),
                    DatePicker::make('until')->label(__('affiliate_panel.until')),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('paid_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('paid_at', '<=', $date))),
            ])
            ->defaultSort('paid_at', 'desc')
            ->emptyStateHeading(__('transactions.empty_heading'))
            ->emptyStateDescription(__('affiliate_panel.empty_sales'))
            ->recordActions([ViewAction::make()->label(__('transactions.actions.view'))]);
    }
}
