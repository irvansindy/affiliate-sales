<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Pages;

use App\Actions\RecordTransaction;
use App\Filament\Admin\Resources\Transactions\TransactionResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RecordTransaction::class)->handle([...$data, 'source' => 'manual'], Filament::auth()->user());
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->errors() as $field => $messages) {
                $errors[$this->form->getStatePath().'.'.$field] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }
}
