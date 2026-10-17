<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AffiliateStatus;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CrmActivityPolicy extends ReadOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isAffiliate() &&
            $user->affiliate()->where('status', AffiliateStatus::Active)->exists());
    }

    public function view(User $user, Model $activity): bool
    {
        if (! $activity instanceof CrmActivity) {
            return false;
        }

        return $user->isAdmin() || ($user->isAffiliate() &&
            $user->affiliate()->where('status', AffiliateStatus::Active)
                ->whereHas('leads', fn ($query) => $query->whereKey($activity->lead_id))->exists());
    }

    public function create(User $user, ?CrmLead $lead = null): bool
    {
        return $lead !== null && ($user->isAdmin() || ($user->isAffiliate() &&
            $user->affiliate()->where('status', AffiliateStatus::Active)
                ->whereKey($lead->affiliate_id)->exists()));
    }

    public function update(User $user, Model $activity): bool
    {
        return $this->view($user, $activity);
    }
}
