<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SalesSubmissions\Pages;

use App\Actions\ReviewSalesSubmission;
use App\Enums\PurchaseType;
use App\Enums\SalesSubmissionStatus;
use App\Filament\Admin\Resources\SalesSubmissions\SalesSubmissionResource;
use App\Models\SalesSubmission;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesSubmission extends ViewRecord
{
    protected static string $resource = SalesSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')->label(__('sales_submissions.approve'))->color('success')
                ->authorize('approve')
                ->visible(fn (SalesSubmission $record): bool => $record->status === SalesSubmissionStatus::Pending)
                ->requiresConfirmation()
                ->schema([
                    TextInput::make('external_ref')->label(__('transactions.external_ref'))->required()->maxLength(100),
                    TextInput::make('subscription_plan')->label(__('transactions.subscription_plan'))->maxLength(50)
                        ->required(fn (SalesSubmission $record): bool => $record->purchase_type === PurchaseType::Subscription)
                        ->visible(fn (SalesSubmission $record): bool => $record->purchase_type === PurchaseType::Subscription),
                    TextInput::make('base_amount')->label(__('transactions.base_amount'))->prefix('Rp')->integer()->minValue(0)->required(),
                    TextInput::make('discount_amount')->label(__('transactions.discount_amount'))->prefix('Rp')->integer()->minValue(0)->default(0)->required(),
                    TextInput::make('tax_amount')->label(__('transactions.tax_amount'))->prefix('Rp')->integer()->minValue(0)->default(0)->required(),
                    TextInput::make('total_paid')->label(__('transactions.total_paid'))->prefix('Rp')->integer()->minValue(0)->required(),
                    DateTimePicker::make('paid_at')->label(__('transactions.paid_at'))->timezone('Asia/Jakarta')->required(),
                ])
                ->action(function (SalesSubmission $record, array $data): void {
                    app(ReviewSalesSubmission::class)->approve(Filament::auth()->user(), $record, $data);
                    $record->refresh();
                    Notification::make()->title(__('sales_submissions.approved_success'))->success()->send();
                }),
            Action::make('reject')->label(__('sales_submissions.reject'))->color('danger')
                ->authorize('reject')
                ->visible(fn (SalesSubmission $record): bool => $record->status === SalesSubmissionStatus::Pending)
                ->requiresConfirmation()
                ->schema([
                    TextInput::make('reason')->label(__('sales_submissions.rejection_reason'))->required()->maxLength(1000),
                ])
                ->action(function (SalesSubmission $record, array $data): void {
                    app(ReviewSalesSubmission::class)->reject(Filament::auth()->user(), $record, $data['reason']);
                    $record->refresh();
                    Notification::make()->title(__('sales_submissions.rejected_success'))->success()->send();
                }),
        ];
    }
}
