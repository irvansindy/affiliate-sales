<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AffiliateStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterAffiliate
{
    /** @param array{name: string, email: string, password: string} $data */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->forceFill(['role' => UserRole::Affiliate])->save();
            $user->affiliate()->create(['status' => AffiliateStatus::Pending]);

            return $user;
        });
    }
}
