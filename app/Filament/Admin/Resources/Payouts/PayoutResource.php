<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payouts;

use App\Filament\Admin\Resources\Payouts\Pages\CreatePayout;
use App\Filament\Admin\Resources\Payouts\Pages\ListPayouts;
use App\Filament\Admin\Resources\Payouts\Pages\ViewPayout;
use App\Filament\Admin\Resources\Payouts\Schemas\PayoutForm;
use App\Filament\Admin\Resources\Payouts\Schemas\PayoutInfolist;
use App\Filament\Admin\Resources\Payouts\Tables\PayoutsTable;
use App\Models\Payout;
use BackedEnum;
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
        return parent::getEloquentQuery()->with('affiliate.user');
    }

    public static function form(Schema $schema): Schema
    {
        return PayoutForm::configure($schema);
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
            'create' => CreatePayout::route('/create'),
            'view' => ViewPayout::route('/{record}'),
        ];
    }
}
