<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\Commissions;

use App\Filament\Admin\Resources\Commissions\Schemas\CommissionInfolist;
use App\Filament\Admin\Resources\Commissions\Tables\CommissionsTable;
use App\Filament\Affiliate\Resources\Commissions\Pages\ListCommissions;
use App\Filament\Affiliate\Resources\Commissions\Pages\ViewCommission;
use App\Models\Commission;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getModelLabel(): string
    {
        return __('commissions.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('commissions.plural_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('affiliate_id', Filament::auth()->user()->affiliate->getKey())
            ->with(['transaction.product', 'affiliate.user']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CommissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissions::route('/'),
            'view' => ViewCommission::route('/{record}'),
        ];
    }
}
