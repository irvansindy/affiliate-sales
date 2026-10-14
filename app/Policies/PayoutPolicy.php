<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\User;

class PayoutPolicy extends AffiliateOwnedPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function markPaid(User $user, Payout $payout): bool
    {
        return $user->isAdmin() && $payout->status === PayoutStatus::Processing;
    }

    public function markFailed(User $user, Payout $payout): bool
    {
        return $user->isAdmin() && $payout->status === PayoutStatus::Processing;
    }
}
