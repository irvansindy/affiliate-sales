<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Schemas;

use App\Enums\PurchaseType;
use App\Enums\SaleType;
use App\Enums\TransactionSource;
use App\Enums\TransactionStatus;
use App\Services\DisplayFormatter;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextEntry::make('external_ref')->label(__('transactions.external_ref')),
            TextEntry::make('source')->label(__('transactions.source'))
                ->formatStateUsing(fn (TransactionSource $state): string => __('transactions.sources.'.$state->value)),
            TextEntry::make('product.name')->label(__('transactions.product')),
            TextEntry::make('affiliate.user.name')->label(__('transactions.affiliate'))->placeholder('—'),
            TextEntry::make('customer_name')->label(__('transactions.customer_name')),
            TextEntry::make('customer_email')->label(__('transactions.customer_email')),
            TextEntry::make('purchase_type')->label(__('transactions.purchase_type'))
                ->formatStateUsing(fn (PurchaseType $state): string => __('transactions.purchase_types.'.$state->value)),
            TextEntry::make('sale_type')->label(__('transactions.sale_type'))
                ->formatStateUsing(fn (SaleType $state): string => __('transactions.sale_types.'.$state->value)),
            TextEntry::make('subscription_plan')->label(__('transactions.subscription_plan'))->placeholder('—'),
            TextEntry::make('subscription_cycle')->label(__('transactions.subscription_cycle'))->placeholder('—'),
            TextEntry::make('base_amount')->label(__('transactions.base_amount'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::rupiah((int) $state)),
            TextEntry::make('discount_amount')->label(__('transactions.discount_amount'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::rupiah((int) $state)),
            TextEntry::make('tax_amount')->label(__('transactions.tax_amount'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::rupiah((int) $state)),
            TextEntry::make('total_paid')->label(__('transactions.total_paid'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::rupiah((int) $state)),
            TextEntry::make('status')->label(__('transactions.status'))
                ->formatStateUsing(fn (TransactionStatus $state): string => __('transactions.statuses.'.$state->value)),
            TextEntry::make('paid_at')->label(__('transactions.paid_at'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state)),
            TextEntry::make('review_note')->label(__('transactions.review_note'))->placeholder('—'),
        ]);
    }
}
