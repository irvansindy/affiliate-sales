<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SalesSubmissions;

use App\Filament\Admin\Resources\SalesSubmissions\Pages\ListSalesSubmissions;
use App\Filament\Admin\Resources\SalesSubmissions\Pages\ViewSalesSubmission;
use App\Filament\Admin\Resources\SalesSubmissions\Schemas\SalesSubmissionInfolist;
use App\Filament\Admin\Resources\SalesSubmissions\Tables\SalesSubmissionsTable;
use App\Models\SalesSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesSubmissionResource extends Resource
{
    protected static ?string $model = SalesSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function getModelLabel(): string
    {
        return __('sales_submissions.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sales_submissions.plural_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['affiliate.user', 'product', 'transaction']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesSubmissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesSubmissions::route('/'),
            'view' => ViewSalesSubmission::route('/{record}'),
        ];
    }
}
