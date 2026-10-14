<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Commissions\Pages;

use App\Filament\Admin\Resources\Commissions\CommissionResource;
use App\Filament\Exports\CommissionExporter;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ListRecords;

class ListCommissions extends ListRecords
{
    protected static string $resource = CommissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export')->label(__('admin_reports.export_commissions'))
                ->exporter(CommissionExporter::class)->fileDisk('local')
                ->formats([ExportFormat::Csv, ExportFormat::Xlsx])
                ->authorize(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->modifyQueryUsing(function ($query) {
                    abort_unless(auth()->user()?->isAdmin(), 403);

                    return $query;
                }),
        ];
    }
}
