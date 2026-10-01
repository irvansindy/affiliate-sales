<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\UserRole;
use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilamentPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_admin_panel(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_admin_is_forbidden_from_affiliate_panel(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);

        $this->actingAs($admin)
            ->get('/affiliate')
            ->assertForbidden();
    }

    public function test_active_affiliate_can_open_affiliate_panel(): void
    {
        $affiliate = $this->affiliateUser(AffiliateStatus::Active);

        $this->actingAs($affiliate)
            ->get('/affiliate')
            ->assertOk();
    }

    public function test_affiliate_is_forbidden_from_admin_panel(): void
    {
        $affiliate = $this->affiliateUser(AffiliateStatus::Active);

        $this->actingAs($affiliate)
            ->get('/admin')
            ->assertForbidden();
    }

    #[DataProvider('inactiveAffiliateStatuses')]
    public function test_non_active_affiliate_is_forbidden_from_affiliate_panel(AffiliateStatus $status): void
    {
        $affiliate = $this->affiliateUser($status);

        $this->actingAs($affiliate)
            ->get('/affiliate')
            ->assertForbidden();
    }

    public function test_affiliate_without_profile_is_forbidden_from_affiliate_panel(): void
    {
        $affiliate = $this->userWithRole(UserRole::Affiliate);

        $this->actingAs($affiliate)
            ->get('/affiliate')
            ->assertForbidden();
    }

    /**
     * @return array<string, array{AffiliateStatus}>
     */
    public static function inactiveAffiliateStatuses(): array
    {
        return [
            'pending' => [AffiliateStatus::Pending],
            'suspended' => [AffiliateStatus::Suspended],
            'rejected' => [AffiliateStatus::Rejected],
        ];
    }

    private function userWithRole(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function affiliateUser(AffiliateStatus $status): User
    {
        $user = $this->userWithRole(UserRole::Affiliate);

        Affiliate::create([
            'user_id' => $user->id,
            'status' => $status,
        ]);

        return $user;
    }
}
