<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CrmActivityType;
use App\Models\CrmActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CompleteCrmFollowUp
{
    public function __construct(private readonly WriteAuditLog $auditLog) {}

    public function handle(User $actor, CrmActivity $activity): CrmActivity
    {
        Gate::forUser($actor)->authorize('update', $activity);

        return DB::transaction(function () use ($actor, $activity): CrmActivity {
            $activity = CrmActivity::query()->lockForUpdate()->findOrFail($activity->id);
            if ($activity->type !== CrmActivityType::FollowUp || $activity->completed_at !== null) {
                throw ValidationException::withMessages(['activity' => __('crm.validation.not_open_follow_up')]);
            }

            $activity->update(['completed_at' => now()]);
            $this->auditLog->handle($actor, $activity, 'crm_activity.completed', [], ['completed_at' => $activity->completed_at]);

            return $activity;
        });
    }
}
