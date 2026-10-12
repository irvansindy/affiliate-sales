<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AffiliateStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AffiliateUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $user = User::query()->firstOrNew([
            'email' => 'affiliate@affiliatehub.test',
        ]);

        $user->fill([
            'name' => 'Affiliate Demo',
            'password' => 'password',
        ]);

        $user->forceFill([
            'role' => UserRole::Affiliate,
            'email_verified_at' => now(),
        ])->save();

        $user->affiliate()->updateOrCreate([], [
            'referral_code' => 'DEMO01',
            'status' => AffiliateStatus::Active,
            'approved_at' => now(),
        ]);
    }
}
