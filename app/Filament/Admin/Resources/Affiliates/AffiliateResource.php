<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Affiliates;

use App\Filament\Admin\Resources\Affiliates\Pages\ListAffiliates;
use App\Filament\Admin\Resources\Affiliates\Pages\ViewAffiliate;
use App\Filament\Admin\Resources\Affiliates\Schemas\AffiliateInfolist;
use App\Filament\Admin\Resources\Affiliates\Tables\AffiliatesTable;
use App\Models\Affiliate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AffiliateResource extends Resource
{
    protected static ?string $model = Affiliate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getModelLabel(): string
    {
        return __('affiliates.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('affiliates.plural_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliates::route('/'),
            'view' => ViewAffiliate::route('/{record}'),
        ];
    }
}
