<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payouts\Tables;

use App\Enums\PayoutStatus;
use App\Services\DisplayFormatter;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label(__('payouts.number'))->sortable(),
                TextColumn::make('affiliate.user.name')->label(__('payouts.affiliate'))->searchable(),
                TextColumn::make('total_amount')->label(__('payouts.total'))
                    ->formatStateUsing(fn ($state): string => DisplayFormatter::rupiah((int) $state))->sortable(),
                TextColumn::make('status')->label(__('payouts.status'))->badge()
                    ->formatStateUsing(fn (PayoutStatus $state): string => __('payouts.statuses.'.$state->value))
                    ->color(fn (PayoutStatus $state): string => match ($state) {
                        PayoutStatus::Processing => 'warning',
                        PayoutStatus::Paid => 'success',
                        PayoutStatus::Failed => 'danger',
                    }),
                TextColumn::make('created_at')->label(__('payouts.created_at'))
                    ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state))->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('payouts.status'))->options(
                    collect(PayoutStatus::cases())->mapWithKeys(fn (PayoutStatus $status): array => [
                        $status->value => __('payouts.statuses.'.$status->value),
                    ])->all()
                ),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('payouts.empty_heading'))
            ->recordActions([ViewAction::make()->label(__('payouts.view'))]);
    }
}
