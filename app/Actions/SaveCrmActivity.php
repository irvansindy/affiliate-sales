<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CrmActivityType;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveCrmActivity
{
    public function __construct(private readonly WriteAuditLog $auditLog) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, CrmLead $lead, array $input): CrmActivity
    {
        Gate::forUser($actor)->authorize('create', [CrmActivity::class, $lead]);

        $validated = Validator::make($input, [
            'type' => ['required', Rule::enum(CrmActivityType::class)],
            'summary' => ['required', 'string', 'max:10000'],
            'occurred_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
        ])->validate();

        if ($validated['type'] === CrmActivityType::FollowUp->value && empty($validated['due_at'])) {
            throw ValidationException::withMessages(['due_at' => __('crm.validation.due_required')]);
        }

        if ($validated['type'] === CrmActivityType::Interaction->value && ! empty($validated['due_at'])) {
            throw ValidationException::withMessages(['due_at' => __('crm.validation.due_for_follow_up_only')]);
        }

        return DB::transaction(function () use ($actor, $lead, $validated): CrmActivity {
            $activity = CrmActivity::query()->create([
                ...$validated,
                'lead_id' => $lead->id,
                'author_user_id' => $actor->id,
            ]);
            $this->auditLog->handle($actor, $activity, 'crm_activity.created', [], $activity->only(['lead_id', 'type', 'summary', 'occurred_at', 'due_at']));

            return $activity;
        });
    }
}
