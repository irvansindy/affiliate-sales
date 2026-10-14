<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Enums\PurchaseType;
use App\Enums\SaleType;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\DisplayFormatter;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TransactionExporter extends Exporter
{
    protected static ?string $model = Transaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('external_ref')->label(__('transactions.external_ref'))->preventFormulaInjection(),
            ExportColumn::make('product.name')->label(__('transactions.product'))->preventFormulaInjection(),
            ExportColumn::make('affiliate.user.name')->label(__('transactions.affiliate'))->preventFormulaInjection(),
            ExportColumn::make('customer_name')->label(__('transactions.customer_name'))->preventFormulaInjection(),
            ExportColumn::make('customer_email')->label(__('transactions.customer_email'))->preventFormulaInjection(),
            ExportColumn::make('purchase_type')->label(__('transactions.purchase_type'))
                ->formatStateUsing(fn (PurchaseType $state): string => __('transactions.purchase_types.'.$state->value)),
            ExportColumn::make('sale_type')->label(__('transactions.sale_type'))
                ->formatStateUsing(fn (SaleType $state): string => __('transactions.sale_types.'.$state->value)),
            ExportColumn::make('base_amount')->label(__('transactions.base_amount')),
            ExportColumn::make('discount_amount')->label(__('transactions.discount_amount')),
            ExportColumn::make('tax_amount')->label(__('transactions.tax_amount')),
            ExportColumn::make('total_paid')->label(__('transactions.total_paid')),
            ExportColumn::make('status')->label(__('transactions.status'))
                ->formatStateUsing(fn (TransactionStatus $state): string => __('transactions.statuses.'.$state->value)),
            ExportColumn::make('paid_at')->label(__('transactions.paid_at'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return __('admin_reports.export_completed', [
            'count' => $export->successful_rows,
            'failed' => $export->getFailedRowsCount(),
        ]);
    }
}
