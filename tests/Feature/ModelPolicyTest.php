<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\UserRole;
use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModelPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_review_affiliates_but_affiliate_cannot_review_another_profile(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Affiliate]);
        $other = User::factory()->create(['role' => UserRole::Affiliate]);
        $affiliate = Affiliate::create(['user_id' => $owner->id, 'status' => AffiliateStatus::Active]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $affiliate));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $affiliate));
        $this->assertFalse(Gate::forUser($other)->allows('view', $affiliate));
        $this->assertFalse(Gate::forUser($owner)->allows('viewAny', Affiliate::class));
        $this->assertFalse(Gate::forUser($owner)->allows('approve', $affiliate));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $affiliate));
    }

    public function test_all_existing_models_have_discoverable_policies(): void
    {
        foreach ([
            'User', 'Affiliate', 'Product', 'ReferralClick', 'Transaction',
            'Commission', 'Payout', 'WebhookEvent', 'AuditLog', 'Setting',
        ] as $model) {
            $this->assertNotNull(Gate::getPolicyFor('App\\Models\\'.$model), $model);
        }
    }

    #[DataProvider('affiliateOwnedModels')]
    public function test_active_affiliate_can_view_own_records_only(string $model): void
    {
        $owner = User::factory()->create(['role' => UserRole::Affiliate]);
        $other = User::factory()->create(['role' => UserRole::Affiliate]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = Affiliate::create(['user_id' => $owner->id, 'status' => AffiliateStatus::Active]);
        $record = new $model(['affiliate_id' => $affiliate->id]);
        $unattributed = new $model;

        $this->assertTrue(Gate::forUser($owner)->allows('view', $record));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $record));
        $this->assertFalse(Gate::forUser($other)->allows('view', $record));
        $this->assertFalse(Gate::forUser($owner)->allows('view', $unattributed));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $record));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $record));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $record));

        $affiliate->update(['status' => AffiliateStatus::Suspended]);
        $this->assertFalse(Gate::forUser($owner)->allows('view', $record));
        $this->assertFalse(Gate::forUser($owner)->allows('viewAny', $model));
    }

    public static function affiliateOwnedModels(): array
    {
        return [
            ['App\\Models\\Transaction'],
            ['App\\Models\\Commission'],
            ['App\\Models\\Payout'],
            ['App\\Models\\ReferralClick'],
        ];
    }

    #[DataProvider('adminOnlyModels')]
    public function test_internal_records_are_readable_only_by_admin(string $model): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = User::factory()->create(['role' => UserRole::Affiliate]);
        $record = new $model;

        $this->assertTrue(Gate::forUser($admin)->allows('view', $record));
        $this->assertFalse(Gate::forUser($affiliate)->allows('view', $record));
        $this->assertFalse(Gate::forUser($affiliate)->allows('viewAny', $model));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $record));
    }

    public static function adminOnlyModels(): array
    {
        return [
            ['App\\Models\\Product'],
            ['App\\Models\\Setting'],
            ['App\\Models\\AuditLog'],
            ['App\\Models\\WebhookEvent'],
        ];
    }
}
