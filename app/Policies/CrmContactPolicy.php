<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AffiliateStatus;
use App\Models\CrmContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CrmContactPolicy extends AffiliateOwnedPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin() || ($user->isAffiliate() &&
            $user->affiliate()->where('status', AffiliateStatus::Active)->exists());
    }

    public function update(User $user, Model $contact): bool
    {
        return $contact instanceof CrmContact && $this->view($user, $contact);
    }
}
