<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\Products;

use App\Filament\Affiliate\Resources\Products\Pages\ListProducts;
use App\Filament\Affiliate\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    public static function getNavigationLabel(): string
    {
        return __('referrals.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('referrals.product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('referrals.navigation');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_active', true);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
        ];
    }
}
