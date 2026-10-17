<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\CrmLeads\Schemas;

use App\Enums\CrmLeadStage;
use App\Models\CrmContact;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CrmLeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            Select::make('contact_id')->label(__('crm.contact'))->required()->searchable()
                ->options(fn (): array => CrmContact::query()->where('affiliate_id', Filament::auth()->user()->affiliate->id)->pluck('name', 'id')->all()),
            Select::make('product_id')->label(__('crm.product'))->required()->searchable()
                ->options(fn (): array => Product::query()->pluck('name', 'id')->all()),
            Select::make('stage')->label(__('crm.stage'))->required()->default(CrmLeadStage::New->value)
                ->options(fn (): array => collect(CrmLeadStage::cases())->mapWithKeys(fn (CrmLeadStage $stage): array => [$stage->value => __('crm.stages.'.$stage->value)])->all()),
            Textarea::make('notes')->label(__('crm.notes'))->maxLength(10000)->columnSpanFull(),
        ]);
    }
}
