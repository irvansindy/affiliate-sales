<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payouts\Pages;

use App\Actions\FailPayout;
use App\Actions\MarkPayoutPaid;
use App\Enums\PayoutStatus;
use App\Filament\Admin\Resources\Payouts\PayoutResource;
use App\Models\Payout;
use App\Services\DisplayFormatter;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewPayout extends ViewRecord
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markPaid')->label(__('payouts.mark_paid'))->color('success')
                ->authorize('markPaid')->visible(fn (Payout $record): bool => $record->status === PayoutStatus::Processing)
                ->requiresConfirmation()
                ->modalDescription(fn (Payout $record): string => __('payouts.confirm_paid', ['amount' => DisplayFormatter::rupiah($record->total_amount)]))
                ->schema([
                    FileUpload::make('proof')->label(__('payouts.proof'))->required()
                        ->disk('local')->directory('payout-proofs')->visibility('private')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(5120),
                ])
                ->action(function (Payout $record, array $data): void {
                    try {
                        app(MarkPayoutPaid::class)->handle(Filament::auth()->user(), $record, $data['proof']);
                        $record->refresh();
                        Notification::make()->title(__('payouts.paid_success'))->success()->send();
                    } catch (ValidationException) {
                        $record->refresh();
                        Notification::make()->title(__('payouts.invalid_transition'))->danger()->persistent()->send();
                    }
                }),
            Action::make('markFailed')->label(__('payouts.mark_failed'))->color('danger')
                ->authorize('markFailed')->visible(fn (Payout $record): bool => $record->status === PayoutStatus::Processing)
                ->requiresConfirmation()
                ->modalDescription(fn (Payout $record): string => __('payouts.confirm_failed', ['amount' => DisplayFormatter::rupiah($record->total_amount)]))
                ->action(function (Payout $record): void {
                    app(FailPayout::class)->handle(Filament::auth()->user(), $record);
                    $record->refresh();
                    Notification::make()->title(__('payouts.failed_success'))->success()->send();
                }),
        ];
    }
}
