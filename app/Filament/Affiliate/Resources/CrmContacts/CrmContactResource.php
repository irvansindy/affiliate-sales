<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\CrmContacts;

use App\Filament\Affiliate\Resources\CrmContacts\Pages\CreateCrmContact;
use App\Filament\Affiliate\Resources\CrmContacts\Pages\EditCrmContact;
use App\Filament\Affiliate\Resources\CrmContacts\Pages\ListCrmContacts;
use App\Filament\Affiliate\Resources\CrmContacts\Schemas\CrmContactForm;
use App\Filament\Affiliate\Resources\CrmContacts\Tables\CrmContactsTable;
use App\Models\CrmContact;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CrmContactResource extends Resource
{
    protected static ?string $model = CrmContact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('crm.contact');
    }

    public static function getPluralModelLabel(): string
    {
        return __('crm.contacts');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('affiliate_id', Filament::auth()->user()->affiliate->id);
    }

    public static function form(Schema $schema): Schema
    {
        return CrmContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrmContactsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmContacts::route('/'),
            'create' => CreateCrmContact::route('/create'),
            'edit' => EditCrmContact::route('/{record}/edit'),
        ];
    }
}
