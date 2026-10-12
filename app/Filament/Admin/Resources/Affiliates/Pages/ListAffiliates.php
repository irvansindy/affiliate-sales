<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Affiliates\Pages;

use App\Filament\Admin\Resources\Affiliates\AffiliateResource;
use Filament\Resources\Pages\ListRecords;

class ListAffiliates extends ListRecords
{
    protected static string $resource = AffiliateResource::class;
}
