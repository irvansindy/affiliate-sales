<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Affiliates\Pages;

use App\Filament\Admin\Resources\Affiliates\AffiliateResource;
use App\Filament\Admin\Resources\Affiliates\AffiliateStatusActions;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliate extends ViewRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return AffiliateStatusActions::make();
    }
}
