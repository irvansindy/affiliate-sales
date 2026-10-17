<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\CrmLeads\RelationManagers;

use App\Actions\CompleteCrmFollowUp;
use App\Actions\SaveCrmActivity;
use App\Enums\CrmActivityType;
use App\Models\CrmActivity;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::forUser(Filament::auth()->user())->allows('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('summary')->columns([
            TextColumn::make('type')->label(__('crm.type'))->badge()->formatStateUsing(fn ($state): string => __('crm.types.'.$state->value)),
            TextColumn::make('summary')->label(__('crm.summary'))->searchable(),
            TextColumn::make('occurred_at')->label(__('crm.occurred_at'))->dateTime('d M Y H:i')->visibleFrom('lg'),
            TextColumn::make('due_at')->label(__('crm.due_at'))->dateTime('d M Y H:i')->visibleFrom('md'),
            TextColumn::make('completed_at')->label(__('crm.completed_at'))->dateTime('d M Y H:i')->visibleFrom('lg'),
        ])->headerActions([
            Action::make('addActivity')->label(__('crm.activity'))->schema([
                Select::make('type')->label(__('crm.type'))->options(fn (): array => collect(CrmActivityType::cases())->mapWithKeys(fn (CrmActivityType $type): array => [$type->value => __('crm.types.'.$type->value)])->all())->required(),
                Textarea::make('summary')->label(__('crm.summary'))->required()->maxLength(10000),
                DateTimePicker::make('occurred_at')->label(__('crm.occurred_at')),
                DateTimePicker::make('due_at')->label(__('crm.due_at')),
            ])->action(fn (array $data) => app(SaveCrmActivity::class)->handle(Filament::auth()->user(), $this->getOwnerRecord(), $data)),
        ])->recordActions([
            Action::make('complete')->label(__('crm.complete'))->visible(fn (CrmActivity $record): bool => $record->type === CrmActivityType::FollowUp && $record->completed_at === null)
                ->requiresConfirmation()->action(fn (CrmActivity $record) => app(CompleteCrmFollowUp::class)->handle(Filament::auth()->user(), $record)),
        ])->defaultPaginationPageOption(25)->emptyStateHeading(__('crm.empty_activities'));
    }
}
