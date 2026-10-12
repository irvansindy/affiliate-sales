<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Products\Tables;

use App\Enums\ProductType;
use App\Services\DisplayFormatter;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('products.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('products.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('products.type'))
                    ->badge()
                    ->formatStateUsing(
                        fn (ProductType $state): string => __('products.types.'.$state->value)
                    )
                    ->color(fn (ProductType $state): string => match ($state) {
                        ProductType::Website => 'info',
                        ProductType::MobileApp => 'primary',
                    }),
                TextColumn::make('is_active')
                    ->label(__('products.status'))
                    ->badge()
                    ->formatStateUsing(
                        fn (bool $state): string => $state
                            ? __('products.statuses.active')
                            : __('products.statuses.inactive')
                    )
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('updated_at')
                    ->label(__('products.updated_at'))
                    ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state))
                    ->sortable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('products.type'))
                    ->options([
                        ProductType::Website->value => __('products.types.website'),
                        ProductType::MobileApp->value => __('products.types.mobile_app'),
                    ]),
                SelectFilter::make('is_active')
                    ->label(__('products.status'))
                    ->options([
                        '1' => __('products.statuses.active'),
                        '0' => __('products.statuses.inactive'),
                    ]),
            ])
            ->defaultSort('name')
            ->emptyStateHeading(__('products.empty_heading'))
            ->emptyStateDescription(__('products.empty_description'))
            ->recordActions([
                EditAction::make()->label(__('products.actions.edit')),
            ]);
    }
}
