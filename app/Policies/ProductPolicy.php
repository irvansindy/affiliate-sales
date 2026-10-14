<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AffiliateStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProductPolicy extends ReadOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isAffiliate() &&
            $user->affiliate()->where('status', AffiliateStatus::Active)->exists());
    }

    public function view(User $user, Model $record): bool
    {
        return $user->isAdmin() || ($user->isAffiliate() &&
            $record->getAttribute('is_active') &&
            $user->affiliate()->where('status', AffiliateStatus::Active)->exists());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Model $record): bool
    {
        return $user->isAdmin();
    }
}
