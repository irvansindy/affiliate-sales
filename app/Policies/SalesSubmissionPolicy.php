<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AffiliateStatus;
use App\Models\SalesSubmission;
use App\Models\User;

class SalesSubmissionPolicy extends AffiliateOwnedPolicy
{
    public function create(User $user): bool
    {
        return $user->isAffiliate() && $user->affiliate()->where('status', AffiliateStatus::Active)->exists();
    }

    public function approve(User $user, SalesSubmission $submission): bool
    {
        return $user->isAdmin();
    }

    public function reject(User $user, SalesSubmission $submission): bool
    {
        return $user->isAdmin();
    }
}
