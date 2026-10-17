<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\CrmContacts\Pages;

use App\Actions\SaveCrmContact;
use App\Filament\Affiliate\Resources\CrmContacts\CrmContactResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateCrmContact extends CreateRecord
{
    protected static string $resource = CrmContactResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SaveCrmContact::class)->handle(Filament::auth()->user(), $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn (array $messages, string $field): array => [$this->form->getStatePath().'.'.$field => $messages]
            )->all());
        }
    }
}
