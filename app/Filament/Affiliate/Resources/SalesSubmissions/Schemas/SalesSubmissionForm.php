<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\SalesSubmissions\Schemas;

use App\Enums\PurchaseType;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SalesSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')->label(__('transactions.product'))
                    ->options(fn (): array => Product::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->required(),
                Select::make('purchase_type')->label(__('transactions.purchase_type'))
                    ->options([
                        PurchaseType::Subscription->value => __('transactions.purchase_types.subscription'),
                        PurchaseType::OneTime->value => __('transactions.purchase_types.one_time'),
                    ])->required(),
                TextInput::make('customer_name')->label(__('transactions.customer_name'))->required()->maxLength(150),
                TextInput::make('customer_email')->label(__('transactions.customer_email'))->email()->required()->maxLength(150),
                TextInput::make('claimed_ref')->label(__('sales_submissions.claimed_ref'))->maxLength(100),
                FileUpload::make('proof_path')->label(__('sales_submissions.proof'))->required()
                    ->disk('local')->directory(fn (): string => 'sales-proofs/'.Filament::auth()->user()->affiliate->id)->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(5120),
            ])->columns(['default' => 1, 'md' => 2]);
    }
}
