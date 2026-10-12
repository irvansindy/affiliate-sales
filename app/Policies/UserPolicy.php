<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends ReadOnlyPolicy
{
    public function view(User $user, Model $record): bool
    {
        return $user->isAdmin() || $user->getKey() === $record->getKey();
    }
}
