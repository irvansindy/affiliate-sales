<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $admin = User::query()->firstOrNew([
            'email' => 'admin@affiliatehub.test',
        ]);

        $admin->fill([
            'name' => 'Admin Demo',
            'password' => 'password',
        ]);

        $admin->forceFill([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ])->save();
    }
}
