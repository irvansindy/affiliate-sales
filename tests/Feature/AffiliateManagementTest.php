<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ChangeAffiliateStatus;
use App\Enums\AffiliateStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\Affiliates\Pages\ListAffiliates;
use App\Filament\Admin\Resources\Affiliates\Pages\ViewAffiliate;
use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AffiliateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_view_affiliates_without_exposing_bank_number(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(AffiliateStatus::Pending);
        $affiliate->update(['bank_account_number' => '1234567890123456']);

        $this->actingAs($admin)->get('/admin/affiliates')
            ->assertOk()->assertSee('lang="id"', false);
        $this->get('/admin/affiliates/'.$affiliate->id)
            ->assertOk()->assertDontSee('1234567890123456')->assertSee('****3456');
    }

    public function test_affiliate_cannot_open_admin_affiliate_resource(): void
    {
        $affiliate = $this->affiliate(AffiliateStatus::Active);

        $this->actingAs($affiliate->user)->get('/admin/affiliates')->assertForbidden();
        $this->get('/admin/affiliates/'.$affiliate->id)->assertForbidden();
    }

    #[DataProvider('allowedTransitions')]
    public function test_status_action_updates_status_and_audits_actor_and_changes(string $from, string $to): void
    {
        $this->freezeSecond();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(AffiliateStatus::from($from));

        app(ChangeAffiliateStatus::class)->handle($admin, $affiliate, AffiliateStatus::from($to));

        $this->assertSame($to, $affiliate->fresh()->status->value);
        $log = AuditLog::sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($affiliate->id, $log->subject_id);
        $this->assertSame('affiliate.status_changed', $log->action);
        $this->assertSame($from, $log->old['status']);
        $this->assertSame($to, $log->new['status']);
        if ($to === 'active') {
            $this->assertTrue($affiliate->fresh()->approved_at->equalTo(now()));
        }
    }

    public static function allowedTransitions(): array
    {
        return [
            'approve' => ['pending', 'active'],
            'reject' => ['pending', 'rejected'],
            'suspend' => ['active', 'suspended'],
            'reactivate suspended' => ['suspended', 'active'],
            'reactivate rejected' => ['rejected', 'active'],
        ];
    }

    public function test_repeated_status_change_is_a_no_op(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(AffiliateStatus::Pending);
        $action = app(ChangeAffiliateStatus::class);

        $action->handle($admin, $affiliate, AffiliateStatus::Active);
        $approvedAt = $affiliate->fresh()->approved_at;
        $this->travel(1)->hours();
        $action->handle($admin, $affiliate, AffiliateStatus::Active);

        $this->assertSame(1, AuditLog::count());
        $this->assertTrue($affiliate->fresh()->approved_at->equalTo($approvedAt));
    }

    public function test_affiliate_cannot_change_status_through_action(): void
    {
        $affiliate = $this->affiliate(AffiliateStatus::Pending);

        try {
            app(ChangeAffiliateStatus::class)->handle($affiliate->user, $affiliate, AffiliateStatus::Active);
            $this->fail('Status change must be forbidden.');
        } catch (AuthorizationException) {
            $this->assertSame(AffiliateStatus::Pending, $affiliate->fresh()->status);
            $this->assertSame(0, AuditLog::count());
        }
    }

    public function test_suspend_preserves_previous_approval_date(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(AffiliateStatus::Active);
        $affiliate->update(['approved_at' => '2026-10-01 10:00:00']);

        app(ChangeAffiliateStatus::class)->handle($admin, $affiliate, AffiliateStatus::Suspended);

        $this->assertSame('2026-10-01 10:00:00', $affiliate->fresh()->approved_at->format('Y-m-d H:i:s'));
    }

    public function test_admin_can_approve_through_filament_action(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(AffiliateStatus::Pending);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewAffiliate::class, ['record' => $affiliate->id])
            ->callAction('approve')->assertHasNoActionErrors();

        $this->assertSame(AffiliateStatus::Active, $affiliate->fresh()->status);
        $this->assertSame(1, AuditLog::count());
    }

    private function affiliate(AffiliateStatus $status): Affiliate
    {
        return Affiliate::create([
            'user_id' => User::factory()->create(['role' => UserRole::Affiliate])->id,
            'status' => $status,
        ]);
    }

    public function test_status_change_rolls_back_if_audit_insert_fails(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(AffiliateStatus::Pending);
        DB::unprepared("CREATE TEMP TRIGGER fail_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");

        try {
            app(ChangeAffiliateStatus::class)->handle($admin, $affiliate, AffiliateStatus::Active);
            $this->fail('An audit failure must abort the transaction.');
        } catch (QueryException) {
            $this->assertSame(AffiliateStatus::Pending, $affiliate->fresh()->status);
            $this->assertNull($affiliate->fresh()->approved_at);
            $this->assertSame(0, AuditLog::count());
        } finally {
            DB::unprepared('DROP TRIGGER fail_audit');
        }
    }

    public function test_invalid_transition_does_not_change_status_or_write_audit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(AffiliateStatus::Active);

        try {
            app(ChangeAffiliateStatus::class)->handle($admin, $affiliate, AffiliateStatus::Rejected);
            $this->fail('Active affiliates cannot be rejected.');
        } catch (ValidationException) {
            $this->assertSame(AffiliateStatus::Active, $affiliate->fresh()->status);
            $this->assertSame(0, AuditLog::count());
        }
    }

    public function test_suspension_and_reactivation_change_panel_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(AffiliateStatus::Active);
        $this->actingAs($affiliate->user)->get('/affiliate')->assertOk();

        app(ChangeAffiliateStatus::class)->handle($admin, $affiliate, AffiliateStatus::Suspended);
        $this->get('/affiliate')->assertForbidden();
        app(ChangeAffiliateStatus::class)->handle($admin, $affiliate, AffiliateStatus::Active);
        $this->get('/affiliate')->assertOk();
    }

    public function test_admin_can_search_and_filter_affiliate_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $pending = $this->affiliate(AffiliateStatus::Pending);
        $active = $this->affiliate(AffiliateStatus::Active);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListAffiliates::class)
            ->assertCanSeeTableRecords([$pending, $active])
            ->filterTable('status', 'pending')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$active])
            ->resetTableFilters()
            ->searchTable($active->user->email)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    #[DataProvider('reactivatableStatuses')]
    public function test_admin_can_reactivate_through_filament(string $status): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(AffiliateStatus::from($status));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewAffiliate::class, ['record' => $affiliate->id])
            ->assertActionHidden('approve')
            ->callAction('reactivate')->assertHasNoActionErrors();

        $this->assertSame(AffiliateStatus::Active, $affiliate->fresh()->status);
        $this->assertSame($admin->id, AuditLog::sole()->user_id);
    }

    public static function reactivatableStatuses(): array
    {
        return [['suspended'], ['rejected']];
    }

    public function test_affiliate_name_is_escaped_in_admin_detail(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(AffiliateStatus::Pending);
        $affiliate->user->update(['name' => '<script>alert("name")</script>']);

        $this->actingAs($admin)->get('/admin/affiliates/'.$affiliate->id)
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("name")</script>', false);
    }
}
