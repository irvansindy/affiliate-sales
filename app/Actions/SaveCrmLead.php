<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CrmLeadStage;
use App\Models\CrmLead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveCrmLead
{
    public function __construct(private readonly WriteAuditLog $auditLog) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, array $input, ?CrmLead $lead = null): CrmLead
    {
        Gate::forUser($actor)->authorize($lead === null ? 'create' : 'update', $lead ?? CrmLead::class);

        $ownerId = $lead?->affiliate_id ?? ($actor->isAdmin() ? $input['affiliate_id'] ?? null : $actor->affiliate()->firstOrFail()->id);
        $validated = Validator::make($input, [
            'affiliate_id' => $actor->isAdmin() && $lead === null
                ? ['required', 'integer', Rule::exists('affiliates', 'id')]
                : ['nullable', 'integer'],
            'contact_id' => ['required', 'integer', Rule::exists('crm_contacts', 'id')->where('affiliate_id', $ownerId)],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'stage' => ['required', Rule::enum(CrmLeadStage::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ])->validate();

        if ($lead !== null && $actor->isAdmin() &&
            isset($validated['affiliate_id']) && (int) $validated['affiliate_id'] !== $ownerId) {
            throw ValidationException::withMessages(['affiliate_id' => __('crm.validation.owner_immutable')]);
        }

        unset($validated['affiliate_id']);

        return DB::transaction(function () use ($actor, $lead, $ownerId, $validated): CrmLead {
            if ($lead === null) {
                $lead = CrmLead::query()->create([...$validated, 'affiliate_id' => $ownerId]);
                $this->auditLog->handle($actor, $lead, 'crm_lead.created', [], $lead->only(['affiliate_id', 'contact_id', 'product_id', 'stage']));

                return $lead;
            }

            $old = $lead->only(['contact_id', 'product_id', 'stage', 'notes']);
            $lead->update($validated);
            $new = $lead->only(['contact_id', 'product_id', 'stage', 'notes']);
            if ($old !== $new) {
                $this->auditLog->handle($actor, $lead, 'crm_lead.updated', $old, $new);
            }

            return $lead;
        });
    }
}
