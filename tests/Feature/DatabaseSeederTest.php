<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\UserRole;
use App\Models\Affiliate;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\AffiliateUserSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_accounts_for_manually_testing_each_role(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => 'admin@affiliatehub.test']);
        $this->assertDatabaseHas('users', ['email' => 'affiliate@affiliatehub.test']);

        $admin = User::query()->where('email', 'admin@affiliatehub.test')->firstOrFail();
        $affiliateUser = User::query()->where('email', 'affiliate@affiliatehub.test')->firstOrFail();
        $affiliate = $affiliateUser->affiliate()->firstOrFail();

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertNull($admin->affiliate);

        $this->assertSame(UserRole::Affiliate, $affiliateUser->role);
        $this->assertTrue(Hash::check('password', $affiliateUser->password));
        $this->assertSame(AffiliateStatus::Active, $affiliate->status);
        $this->assertSame('DEMO01', $affiliate->referral_code);
        $this->assertNotNull($affiliate->approved_at);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($affiliateUser->canAccessPanel(Filament::getPanel('affiliate')));
    }

    public function test_database_seeder_can_be_run_repeatedly_without_duplicate_demo_accounts(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, User::query()->where('email', 'admin@affiliatehub.test')->count());
        $this->assertSame(1, User::query()->where('email', 'affiliate@affiliatehub.test')->count());
        $this->assertSame(1, Affiliate::query()->where('referral_code', 'DEMO01')->count());
    }

    public function test_demo_account_seeders_do_not_create_predictable_credentials_in_production(): void
    {
        $originalEnvironment = app()->environment();
        $this->app->detectEnvironment(fn (): string => 'production');

        try {
            $this->app->make(AdminUserSeeder::class)->run();
            $this->app->make(AffiliateUserSeeder::class)->run();
        } finally {
            $this->app->detectEnvironment(fn (): string => $originalEnvironment);
        }

        $this->assertDatabaseMissing('users', ['email' => 'admin@affiliatehub.test']);
        $this->assertDatabaseMissing('users', ['email' => 'affiliate@affiliatehub.test']);
    }
}
