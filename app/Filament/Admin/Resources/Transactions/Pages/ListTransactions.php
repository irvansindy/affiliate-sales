<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Pages;

use App\Filament\Admin\Resources\Transactions\TransactionResource;
use App\Filament\Exports\TransactionExporter;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('transactions.actions.create')),
            ExportAction::make('export')->label(__('admin_reports.export_transactions'))
                ->exporter(TransactionExporter::class)->fileDisk('local')
                ->formats([ExportFormat::Csv, ExportFormat::Xlsx])
                ->authorize(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->modifyQueryUsing(function ($query) {
                    abort_unless(auth()->user()?->isAdmin(), 403);

                    return $query;
                }),
        ];
    }
}
