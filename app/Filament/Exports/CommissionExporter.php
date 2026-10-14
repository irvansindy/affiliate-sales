<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Enums\CommissionStatus;
use App\Models\Commission;
use App\Services\DisplayFormatter;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CommissionExporter extends Exporter
{
    protected static ?string $model = Commission::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('transaction.external_ref')->label(__('commissions.transaction'))->preventFormulaInjection(),
            ExportColumn::make('affiliate.user.name')->label(__('commissions.affiliate'))->preventFormulaInjection(),
            ExportColumn::make('transaction.product.name')->label(__('commissions.product'))->preventFormulaInjection(),
            ExportColumn::make('base_amount')->label(__('commissions.base_amount')),
            ExportColumn::make('rate')->label(__('commissions.rate')),
            ExportColumn::make('amount')->label(__('commissions.amount')),
            ExportColumn::make('status')->label(__('commissions.status'))
                ->formatStateUsing(fn (CommissionStatus $state): string => __('commissions.statuses.'.$state->value)),
            ExportColumn::make('approved_at')->label(__('commissions.approved_at'))
                ->formatStateUsing(fn ($state): string => DisplayFormatter::dateTime($state)),
            ExportColumn::make('payout_id')->label(__('admin_reports.payout_number')),
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
