<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Commissions\Schemas;

use App\Enums\CommissionStatus;
use App\Services\DisplayFormatter;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CommissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextEntry::make('transaction.external_ref')->label(__('commissions.transaction')),
            TextEntry::make('transaction.product.name')->label(__('commissions.product')),
            TextEntry::make('affiliate.user.name')->label(__('commissions.affiliate')),
            TextEntry::make('base_amount')->label(__('commissions.base_amount'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::rupiah((int) $state)),
            TextEntry::make('rate')->label(__('commissions.rate'))
                ->formatStateUsing(fn ($state): string => $state.'%'),
            TextEntry::make('amount')->label(__('commissions.amount'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::rupiah((int) $state)),
            TextEntry::make('status')->label(__('commissions.status'))->badge()
                ->formatStateUsing(fn (CommissionStatus $state): string => __('commissions.statuses.'.$state->value))
                ->color(fn (CommissionStatus $state): string => match ($state) {
                    CommissionStatus::Pending => 'warning',
                    CommissionStatus::Approved => 'info',
                    CommissionStatus::Paid => 'success',
                    CommissionStatus::Rejected, CommissionStatus::Cancelled => 'danger',
                }),
            TextEntry::make('approvable_at')->label(__('commissions.approvable_at'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state))->placeholder('—'),
            TextEntry::make('ready_for_review_at')->label(__('commissions.ready_for_review_at'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state))->placeholder('—'),
            TextEntry::make('approved_at')->label(__('commissions.approved_at'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state))->placeholder('—'),
        ]);
    }
}
