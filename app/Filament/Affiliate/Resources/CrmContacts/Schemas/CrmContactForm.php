<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\CrmContacts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CrmContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextInput::make('name')->label(__('crm.name'))->required()->maxLength(150),
            TextInput::make('email')->label(__('crm.email'))->email()->maxLength(150),
            TextInput::make('phone')->label(__('crm.phone'))->tel()->maxLength(30),
            TextInput::make('company')->label(__('crm.company'))->maxLength(150),
        ]);
    }
}
