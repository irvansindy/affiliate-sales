<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\Products\Tables;

use App\Models\Product;
use App\Services\ReferralLinkBuilder;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('referrals.product'))->searchable()->sortable(),
                TextColumn::make('code')->label(__('products.code'))->searchable()->visibleFrom('md'),
                TextColumn::make('referral_link')->label(__('referrals.link'))
                    ->state(fn (Product $record): ?string => app(ReferralLinkBuilder::class)->for(
                        Filament::auth()->user(),
                        Filament::auth()->user()->affiliate,
                        $record,
                    ))
                    ->placeholder(__('referrals.not_ready'))
                    ->copyable(fn (?string $state): bool => filled($state))
                    ->copyMessage(__('referrals.copied'))
                    ->wrap(),
            ])
            ->defaultSort('name')
            ->emptyStateHeading(__('referrals.empty_heading'))
            ->emptyStateDescription(__('referrals.empty_description'));
    }
}
