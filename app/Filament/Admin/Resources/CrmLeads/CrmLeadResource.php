<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CrmLeads;

use App\Filament\Admin\Resources\CrmLeads\Pages\CreateCrmLead;
use App\Filament\Admin\Resources\CrmLeads\Pages\EditCrmLead;
use App\Filament\Admin\Resources\CrmLeads\Pages\ListCrmLeads;
use App\Filament\Admin\Resources\CrmLeads\RelationManagers\ActivitiesRelationManager;
use App\Filament\Admin\Resources\CrmLeads\Schemas\CrmLeadForm;
use App\Filament\Admin\Resources\CrmLeads\Tables\CrmLeadsTable;
use App\Models\CrmLead;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CrmLeadResource extends Resource
{
    protected static ?string $model = CrmLead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('crm.lead');
    }

    public static function getPluralModelLabel(): string
    {
        return __('crm.leads');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['contact', 'product', 'affiliate.user'])
            ->withMin(['activities as next_due_at' => fn (Builder $query) => $query->where('type', 'follow_up')->whereNull('completed_at')], 'due_at');
    }

    public static function form(Schema $schema): Schema
    {
        return CrmLeadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrmLeadsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmLeads::route('/'),
            'create' => CreateCrmLead::route('/create'),
            'edit' => EditCrmLead::route('/{record}/edit'),
        ];
    }
}
