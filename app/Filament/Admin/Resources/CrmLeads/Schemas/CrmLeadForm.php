<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CrmLeads\Schemas;

use App\Enums\CrmLeadStage;
use App\Models\Affiliate;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CrmLeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            Select::make('affiliate_id')->label(__('crm.affiliate'))->required()->searchable()->live()
                ->options(fn (): array => Affiliate::query()->with('user')->get()->mapWithKeys(
                    fn (Affiliate $affiliate): array => [$affiliate->id => $affiliate->user->name.' ('.$affiliate->referral_code.')']
                )->all())
                ->disabled(fn (?CrmLead $record): bool => $record !== null)
                ->dehydrated(fn (?CrmLead $record): bool => $record === null),
            Select::make('contact_id')->label(__('crm.contact'))->required()->searchable()
                ->options(fn (Get $get): array => CrmContact::query()->where('affiliate_id', $get('affiliate_id'))->pluck('name', 'id')->all()),
            Select::make('product_id')->label(__('crm.product'))->required()->searchable()
                ->options(fn (): array => Product::query()->pluck('name', 'id')->all()),
            Select::make('stage')->label(__('crm.stage'))->required()->default(CrmLeadStage::New->value)
                ->options(fn (): array => collect(CrmLeadStage::cases())->mapWithKeys(fn (CrmLeadStage $stage): array => [$stage->value => __('crm.stages.'.$stage->value)])->all()),
            Textarea::make('notes')->label(__('crm.notes'))->maxLength(10000)->columnSpanFull(),
        ]);
    }
}
