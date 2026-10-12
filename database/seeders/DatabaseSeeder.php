<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'default_commission_rate' => '10',   // persen
            'approval_delay_days' => '14',
            'min_payout' => '100000',            // rupiah
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->call([
            AdminUserSeeder::class,
            AffiliateUserSeeder::class,
        ]);

        $email = env('SEED_ADMIN_EMAIL');
        $password = env('SEED_ADMIN_PASSWORD');

        if ($email && $password) {
            $admin = User::firstOrNew(['email' => $email]);
            $admin->fill(['name' => 'Admin', 'password' => $password]);
            $admin->forceFill(['role' => UserRole::Admin, 'email_verified_at' => now()])->save();
        }
    }
}
