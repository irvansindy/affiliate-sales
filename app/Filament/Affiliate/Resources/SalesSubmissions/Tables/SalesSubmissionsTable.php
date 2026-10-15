<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\SalesSubmissions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalesSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('sales_submissions.submitted_at'))->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('product.name')->label(__('transactions.product'))->searchable(),
                TextColumn::make('customer_name')->label(__('transactions.customer_name'))->searchable(),
                TextColumn::make('purchase_type')->label(__('transactions.purchase_type'))
                    ->formatStateUsing(fn ($state): string => __('transactions.purchase_types.'.$state->value)),
                TextColumn::make('status')->label(__('transactions.status'))
                    ->badge()->formatStateUsing(fn ($state): string => __('sales_submissions.status.'.$state->value)),
            ])
            ->emptyStateHeading(__('sales_submissions.empty'))
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
