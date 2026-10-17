<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CrmContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveCrmContact
{
    public function __construct(private readonly WriteAuditLog $auditLog) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, array $input, ?CrmContact $contact = null): CrmContact
    {
        Gate::forUser($actor)->authorize($contact === null ? 'create' : 'update', $contact ?? CrmContact::class);

        $ownerId = $contact?->affiliate_id ?? ($actor->isAdmin() ? $input['affiliate_id'] ?? null : $actor->affiliate()->firstOrFail()->id);
        $validated = Validator::make($input, [
            'affiliate_id' => $actor->isAdmin() && $contact === null
                ? ['required', 'integer', Rule::exists('affiliates', 'id')]
                : ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:150'],
        ])->validate();

        if ($contact !== null && $actor->isAdmin() &&
            isset($validated['affiliate_id']) && (int) $validated['affiliate_id'] !== $ownerId) {
            throw ValidationException::withMessages(['affiliate_id' => __('crm.validation.owner_immutable')]);
        }

        unset($validated['affiliate_id']);

        return DB::transaction(function () use ($actor, $contact, $ownerId, $validated): CrmContact {
            if ($contact === null) {
                $contact = CrmContact::query()->create([...$validated, 'affiliate_id' => $ownerId]);
                $this->auditLog->handle($actor, $contact, 'crm_contact.created', [], $contact->only(['affiliate_id', 'name', 'email', 'phone', 'company']));

                return $contact;
            }

            $old = $contact->only(['name', 'email', 'phone', 'company']);
            $contact->update($validated);
            $new = $contact->only(['name', 'email', 'phone', 'company']);
            if ($old !== $new) {
                $this->auditLog->handle($actor, $contact, 'crm_contact.updated', $old, $new);
            }

            return $contact;
        });
    }
}
