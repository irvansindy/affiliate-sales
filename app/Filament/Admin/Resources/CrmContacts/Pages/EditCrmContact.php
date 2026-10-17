<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CrmContacts\Pages;

use App\Actions\SaveCrmContact;
use App\Filament\Admin\Resources\CrmContacts\CrmContactResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditCrmContact extends EditRecord
{
    protected static string $resource = CrmContactResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SaveCrmContact::class)->handle(Filament::auth()->user(), $data, $record);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn (array $messages, string $field): array => [$this->form->getStatePath().'.'.$field => $messages]
            )->all());
        }
    }
}
