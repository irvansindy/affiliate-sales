<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AffiliateStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class AffiliateOwnedPolicy extends ReadOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isAffiliate() &&
            $user->affiliate()->where('status', AffiliateStatus::Active)->exists());
    }

    public function view(User $user, Model $record): bool
    {
        return $user->isAdmin() || ($user->isAffiliate() &&
            $user->affiliate()->where('status', AffiliateStatus::Active)
                ->whereKey($record->getAttribute('affiliate_id'))->exists());
    }
}
