<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\Payouts;

use App\Filament\Admin\Resources\Payouts\Schemas\PayoutInfolist;
use App\Filament\Admin\Resources\Payouts\Tables\PayoutsTable;
use App\Filament\Affiliate\Resources\Payouts\Pages\ListPayouts;
use App\Filament\Affiliate\Resources\Payouts\Pages\ViewPayout;
use App\Models\Payout;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayoutResource extends Resource
{
    protected static ?string $model = Payout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getModelLabel(): string
    {
        return __('payouts.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payouts.plural_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('affiliate_id', Filament::auth()->user()->affiliate->getKey())
            ->with('affiliate.user');
    }

    public static function infolist(Schema $schema): Schema
    {
        return PayoutInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayoutsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayouts::route('/'),
            'view' => ViewPayout::route('/{record}'),
        ];
    }
}
