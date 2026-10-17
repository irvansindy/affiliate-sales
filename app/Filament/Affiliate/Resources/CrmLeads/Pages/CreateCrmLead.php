<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\CrmLeads\Pages;

use App\Actions\SaveCrmLead;
use App\Filament\Affiliate\Resources\CrmLeads\CrmLeadResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateCrmLead extends CreateRecord
{
    protected static string $resource = CrmLeadResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SaveCrmLead::class)->handle(Filament::auth()->user(), $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn (array $messages, string $field): array => [$this->form->getStatePath().'.'.$field => $messages]
            )->all());
        }
    }
}
