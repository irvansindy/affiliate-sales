<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Schemas;

use App\Enums\PurchaseType;
use App\Enums\SaleType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('external_ref')
                    ->label(__('transactions.external_ref'))
                    ->helperText(__('transactions.external_ref_help'))
                    ->required()
                    ->maxLength(100)
                    ->unique(),
                Select::make('product_id')
                    ->label(__('transactions.product'))
                    ->relationship('product', 'name')
                    ->searchable()
                    ->required(),
                TextInput::make('referral_code')
                    ->label(__('transactions.referral_code'))
                    ->helperText(__('transactions.referral_code_help'))
                    ->maxLength(20),
                Select::make('purchase_type')
                    ->label(__('transactions.purchase_type'))
                    ->options([
                        PurchaseType::Subscription->value => __('transactions.purchase_types.subscription'),
                        PurchaseType::OneTime->value => __('transactions.purchase_types.one_time'),
                    ])
                    ->live()
                    ->required(),
                Select::make('sale_type')
                    ->label(__('transactions.sale_type'))
                    ->options([
                        SaleType::NewSale->value => __('transactions.sale_types.new'),
                        SaleType::Renewal->value => __('transactions.sale_types.renewal'),
                        SaleType::PlanChange->value => __('transactions.sale_types.plan_change'),
                    ])
                    ->required(),
                TextInput::make('subscription_plan')
                    ->label(__('transactions.subscription_plan'))
                    ->maxLength(50)
                    ->required(fn (Get $get): bool => $get('purchase_type') === PurchaseType::Subscription->value)
                    ->visible(fn (Get $get): bool => $get('purchase_type') === PurchaseType::Subscription->value),
                TextInput::make('subscription_cycle')
                    ->label(__('transactions.subscription_cycle'))
                    ->integer()
                    ->minValue(1)
                    ->required(fn (Get $get): bool => $get('purchase_type') === PurchaseType::Subscription->value)
                    ->visible(fn (Get $get): bool => $get('purchase_type') === PurchaseType::Subscription->value),
                TextInput::make('customer_name')
                    ->label(__('transactions.customer_name'))
                    ->required()
                    ->maxLength(150),
                TextInput::make('customer_email')
                    ->label(__('transactions.customer_email'))
                    ->email()
                    ->required()
                    ->maxLength(150),
                TextInput::make('base_amount')
                    ->label(__('transactions.base_amount'))
                    ->prefix('Rp')
                    ->integer()
                    ->minValue(0)
                    ->required(),
                TextInput::make('discount_amount')
                    ->label(__('transactions.discount_amount'))
                    ->prefix('Rp')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                TextInput::make('tax_amount')
                    ->label(__('transactions.tax_amount'))
                    ->prefix('Rp')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                TextInput::make('total_paid')
                    ->label(__('transactions.total_paid'))
                    ->helperText(__('transactions.total_paid_help'))
                    ->prefix('Rp')
                    ->integer()
                    ->minValue(0)
                    ->required(),
                DateTimePicker::make('paid_at')
                    ->label(__('transactions.paid_at'))
                    ->timezone('Asia/Jakarta')
                    ->required(),
            ])
            ->columns(['default' => 1, 'md' => 2]);
    }
}
