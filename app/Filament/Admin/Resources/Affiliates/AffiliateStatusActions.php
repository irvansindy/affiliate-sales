<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Affiliates;

use App\Actions\ChangeAffiliateStatus;
use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class AffiliateStatusActions
{
    /** @return array<Action> */
    public static function make(): array
    {
        return [
            static::statusAction('approve', AffiliateStatus::Active, 'success')
                ->visible(fn (Affiliate $record): bool => $record->status === AffiliateStatus::Pending),
            static::statusAction('reactivate', AffiliateStatus::Active, 'success', 'approve')
                ->visible(fn (Affiliate $record): bool => in_array($record->status, [AffiliateStatus::Suspended, AffiliateStatus::Rejected], true)),
            static::statusAction('reject', AffiliateStatus::Rejected, 'danger')
                ->visible(fn (Affiliate $record): bool => $record->status === AffiliateStatus::Pending),
            static::statusAction('suspend', AffiliateStatus::Suspended, 'danger')
                ->visible(fn (Affiliate $record): bool => $record->status === AffiliateStatus::Active),
        ];
    }

    private static function statusAction(string $name, AffiliateStatus $status, string $color, ?string $ability = null): Action
    {
        return Action::make($name)
            ->label(__('affiliates.actions.'.$name))
            ->color($color)
            ->authorize($ability ?? $name)
            ->requiresConfirmation()
            ->modalHeading(__('affiliates.actions.'.$name))
            ->modalDescription(__('affiliates.confirmation'))
            ->action(function (Affiliate $record) use ($status): void {
                try {
                    app(ChangeAffiliateStatus::class)->handle(Filament::auth()->user(), $record, $status);
                    $record->refresh();
                    Notification::make()->title(__('affiliates.changed'))->success()->duration(4000)->send();
                } catch (ValidationException) {
                    $record->refresh();
                    Notification::make()->title(__('affiliates.invalid_transition'))
                        ->body(__('affiliates.retry'))->danger()->persistent()->send();
                }
            });
    }
}
