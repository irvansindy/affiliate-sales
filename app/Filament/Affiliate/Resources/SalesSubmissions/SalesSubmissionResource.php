<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\SalesSubmissions;

use App\Filament\Admin\Resources\SalesSubmissions\Schemas\SalesSubmissionInfolist;
use App\Filament\Affiliate\Resources\SalesSubmissions\Pages\CreateSalesSubmission;
use App\Filament\Affiliate\Resources\SalesSubmissions\Pages\ListSalesSubmissions;
use App\Filament\Affiliate\Resources\SalesSubmissions\Pages\ViewSalesSubmission;
use App\Filament\Affiliate\Resources\SalesSubmissions\Schemas\SalesSubmissionForm;
use App\Filament\Affiliate\Resources\SalesSubmissions\Tables\SalesSubmissionsTable;
use App\Models\SalesSubmission;
use BackedEnum;
use Filament\Facades\Filament;
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
        return parent::getEloquentQuery()->where('affiliate_id', Filament::auth()->user()->affiliate->id)
            ->with(['product', 'transaction']);
    }

    public static function form(Schema $schema): Schema
    {
        return SalesSubmissionForm::configure($schema);
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
            'create' => CreateSalesSubmission::route('/create'),
            'view' => ViewSalesSubmission::route('/{record}'),
        ];
    }
}
