<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Resources\CrmLeads\Pages;

use App\Actions\SaveCrmLead;
use App\Filament\Affiliate\Resources\CrmLeads\CrmLeadResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditCrmLead extends EditRecord
{
    protected static string $resource = CrmLeadResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SaveCrmLead::class)->handle(Filament::auth()->user(), $data, $record);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn (array $messages, string $field): array => [$this->form->getStatePath().'.'.$field => $messages]
            )->all());
        }
    }
}
