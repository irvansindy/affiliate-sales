<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\SalesSubmissions\Pages;

use App\Actions\SubmitSalesSubmission;
use App\Filament\Affiliate\Resources\SalesSubmissions\SalesSubmissionResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateSalesSubmission extends CreateRecord
{
    protected static string $resource = SalesSubmissionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SubmitSalesSubmission::class)->handle(Filament::auth()->user(), $data);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$this->form->getStatePath().'.'.$field] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }
}
