<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AffiliateStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AffiliatePolicy extends ReadOnlyPolicy
{
    public function view(User $user, Model $record): bool
    {
        return $user->isAdmin() || ($user->isAffiliate() &&
            $record->getAttribute('user_id') === $user->getKey() &&
            $record->getAttribute('status') === AffiliateStatus::Active);
    }

    public function approve(User $user, Model $record): bool
    {
        return $user->isAdmin();
    }

    public function reject(User $user, Model $record): bool
    {
        return $user->isAdmin();
    }

    public function suspend(User $user, Model $record): bool
    {
        return $user->isAdmin();
    }
}
