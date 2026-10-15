<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class SettingPolicy extends ReadOnlyPolicy
{
    public function updateCommissionRate(User $user): bool
    {
        return $user->isAdmin();
    }
}
