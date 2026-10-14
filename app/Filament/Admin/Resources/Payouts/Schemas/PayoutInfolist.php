<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Payouts\Schemas;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Services\DisplayFormatter;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class PayoutInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextEntry::make('affiliate.user.name')->label(__('payouts.affiliate')),
            TextEntry::make('total_amount')->label(__('payouts.total'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::rupiah((int) $state)),
            TextEntry::make('status')->label(__('payouts.status'))->badge()
                ->formatStateUsing(fn (PayoutStatus $state): string => __('payouts.statuses.'.$state->value)),
            TextEntry::make('bank_name')->label(__('payouts.bank')),
            TextEntry::make('bank_account_name')->label(__('payouts.account_name')),
            TextEntry::make('bank_account_number')->label(__('payouts.account_number'))
                ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : '****'.substr($state, -4)),
            TextEntry::make('paid_at')->label(__('payouts.paid_at'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state))->placeholder('—'),
            TextEntry::make('proof_path')->label(__('payouts.proof'))
                ->formatStateUsing(fn (): string => __('payouts.view_proof'))
                ->url(fn (Payout $record): ?string => $record->proof_path === null ? null : Storage::disk('local')->temporaryUrl($record->proof_path, now()->addMinutes(10)))
                ->openUrlInNewTab()->placeholder('—'),
        ]);
    }
}
