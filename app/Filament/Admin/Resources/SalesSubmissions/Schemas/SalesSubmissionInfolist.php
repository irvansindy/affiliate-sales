<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SalesSubmissions\Schemas;

use App\Models\SalesSubmission;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class SalesSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextEntry::make('affiliate.user.name')->label(__('transactions.affiliate')),
            TextEntry::make('product.name')->label(__('transactions.product')),
            TextEntry::make('purchase_type')->label(__('transactions.purchase_type'))
                ->formatStateUsing(fn ($state): string => __('transactions.purchase_types.'.$state->value)),
            TextEntry::make('status')->label(__('transactions.status'))->badge()
                ->formatStateUsing(fn ($state): string => __('sales_submissions.status.'.$state->value)),
            TextEntry::make('customer_name')->label(__('transactions.customer_name')),
            TextEntry::make('customer_email')->label(__('transactions.customer_email')),
            TextEntry::make('claimed_ref')->label(__('sales_submissions.claimed_ref'))->placeholder('—'),
            TextEntry::make('proof_path')->label(__('sales_submissions.proof'))
                ->formatStateUsing(fn (): string => __('sales_submissions.view_proof'))
                ->url(fn (SalesSubmission $record): string => Storage::disk('local')->temporaryUrl($record->proof_path, now()->addMinutes(10)))
                ->openUrlInNewTab(),
            TextEntry::make('transaction.external_ref')->label(__('transactions.external_ref'))->placeholder('—'),
            TextEntry::make('rejection_reason')->label(__('sales_submissions.rejection_reason'))->placeholder('—'),
        ]);
    }
}
