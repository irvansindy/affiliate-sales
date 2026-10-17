<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CrmContacts\Schemas;

use App\Models\Affiliate;
use App\Models\CrmContact;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CrmContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            Select::make('affiliate_id')->label(__('crm.affiliate'))->required()->searchable()
                ->options(fn (): array => Affiliate::query()->with('user')->get()->mapWithKeys(
                    fn (Affiliate $affiliate): array => [$affiliate->id => $affiliate->user->name.' ('.$affiliate->referral_code.')']
                )->all())
                ->disabled(fn (?CrmContact $record): bool => $record !== null)
                ->dehydrated(fn (?CrmContact $record): bool => $record === null),
            TextInput::make('name')->label(__('crm.name'))->required()->maxLength(150),
            TextInput::make('email')->label(__('crm.email'))->email()->maxLength(150),
            TextInput::make('phone')->label(__('crm.phone'))->tel()->maxLength(30),
            TextInput::make('company')->label(__('crm.company'))->maxLength(150),
        ]);
    }
}
