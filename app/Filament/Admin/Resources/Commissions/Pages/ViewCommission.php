<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Commissions\Pages;

use App\Actions\ChangeCommissionStatus;
use App\Enums\CommissionStatus;
use App\Enums\TransactionStatus;
use App\Filament\Admin\Resources\Commissions\CommissionResource;
use App\Models\Commission;
use App\Services\DisplayFormatter;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewCommission extends ViewRecord
{
    protected static string $resource = CommissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->statusAction('approve', CommissionStatus::Approved, 'success')
                ->visible(fn (Commission $record): bool => $record->status === CommissionStatus::Pending
                    && $record->approvable_at !== null
                    && ! $record->approvable_at->isFuture()
                    && $record->transaction->status === TransactionStatus::Paid),
            $this->statusAction('reject', CommissionStatus::Rejected, 'danger')
                ->visible(fn (Commission $record): bool => $record->status === CommissionStatus::Pending),
        ];
    }

    private function statusAction(string $name, CommissionStatus $target, string $color): Action
    {
        return Action::make($name)
            ->label(__('commissions.actions.'.$name))
            ->color($color)
            ->authorize($name)
            ->requiresConfirmation()
            ->modalHeading(__('commissions.actions.'.$name))
            ->modalDescription(fn (Commission $record): string => __('commissions.confirmation', [
                'amount' => DisplayFormatter::rupiah((int) $record->amount),
            ]))
            ->action(function (Commission $record) use ($target): void {
                try {
                    app(ChangeCommissionStatus::class)->handle(Filament::auth()->user(), $record, $target);
                    $record->refresh();
                    Notification::make()->title(__('commissions.changed'))->success()->duration(4000)->send();
                } catch (ValidationException) {
                    $record->refresh();
                    Notification::make()->title(__('commissions.invalid_transition'))
                        ->body(__('commissions.retry'))->danger()->persistent()->send();
                }
            });
    }
}
