<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Products\Schemas;

use App\Enums\ProductType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label(__('products.code'))
                    ->helperText(__('products.code_help'))
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label(__('products.name'))
                    ->helperText(__('products.name_help'))
                    ->required()
                    ->maxLength(150),
                Select::make('type')
                    ->label(__('products.type'))
                    ->helperText(__('products.type_help'))
                    ->options([
                        ProductType::Website->value => __('products.types.website'),
                        ProductType::MobileApp->value => __('products.types.mobile_app'),
                    ])
                    ->required()
                    ->native(false),
                Toggle::make('is_active')
                    ->label(__('products.is_active'))
                    ->helperText(__('products.is_active_help'))
                    ->default(true),
            ])
            ->columns([
                'default' => 1,
                'md' => 2,
            ]);
    }
}
