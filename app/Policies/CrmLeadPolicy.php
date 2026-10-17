<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AffiliateStatus;
use App\Models\CrmLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CrmLeadPolicy extends AffiliateOwnedPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin() || ($user->isAffiliate() &&
            $user->affiliate()->where('status', AffiliateStatus::Active)->exists());
    }

    public function update(User $user, Model $lead): bool
    {
        return $lead instanceof CrmLead && $this->view($user, $lead);
    }
}
