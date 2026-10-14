<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\Products\Pages;

use App\Filament\Affiliate\Resources\Products\ProductResource;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
}
