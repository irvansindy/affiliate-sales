<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CrmLeads\Tables;

use App\Enums\CrmLeadStage;
use App\Models\CrmLead;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CrmLeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('contact.name')->label(__('crm.contact'))->searchable()->sortable(),
            TextColumn::make('product.name')->label(__('crm.product'))->sortable()->visibleFrom('md'),
            TextColumn::make('affiliate.user.name')->label(__('crm.affiliate'))->visibleFrom('lg'),
            TextColumn::make('stage')->label(__('crm.stage'))->badge()->formatStateUsing(fn ($state): string => __('crm.stages.'.$state->value))
                ->color(fn ($state): string => match ($state->value) {
                    'won' => 'success', 'lost' => 'danger', 'proposal', 'negotiation' => 'warning', default => 'info'
                }),
            TextColumn::make('next_due_at')->label(__('crm.next_due_at'))->dateTime('d M Y H:i')->visibleFrom('lg'),
            TextColumn::make('overdue_status')->label(__('crm.overdue'))
                ->state(fn (CrmLead $record): ?string => $record->next_due_at && Carbon::parse($record->next_due_at)->isPast() ? __('crm.overdue') : null)
                ->badge()->color('danger')->visibleFrom('md'),
        ])->filters([
            SelectFilter::make('stage')->label(__('crm.stage'))->options(fn (): array => collect(CrmLeadStage::cases())->mapWithKeys(fn (CrmLeadStage $stage): array => [$stage->value => __('crm.stages.'.$stage->value)])->all()),
            Filter::make('overdue')->label(__('crm.overdue'))->query(fn (Builder $query): Builder => $query->whereHas('activities', fn (Builder $activities): Builder => $activities->where('type', 'follow_up')->whereNull('completed_at')->where('due_at', '<', now()))),
        ])->recordActions([EditAction::make()])->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('crm.empty_leads'))->emptyStateDescription(__('crm.empty_leads_description'));
    }
}
