<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Commission;
use App\Models\User;

class CommissionPolicy extends AffiliateOwnedPolicy
{
    public function approve(User $user, Commission $commission): bool
    {
        return $user->isAdmin();
    }

    public function reject(User $user, Commission $commission): bool
    {
        return $user->isAdmin();
    }
}
