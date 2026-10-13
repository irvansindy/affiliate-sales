<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ChangeCommissionStatus;
use App\Actions\RecordTransaction;
use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\Commissions\Pages\ListCommissions;
use App\Filament\Admin\Resources\Commissions\Pages\ViewCommission;
use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CommissionApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_command_marks_due_pending_commissions_ready_without_approving(): void
    {
        $due = $this->commission('INV-DUE');
        $future = $this->commission('INV-FUTURE');
        $future->forceFill(['approvable_at' => now()->addDay()])->save();

        $this->artisan('commissions:mark-ready')->assertSuccessful();

        $this->assertNotNull($due->fresh()->ready_for_review_at);
        $this->assertSame(CommissionStatus::Pending, $due->fresh()->status);
        $this->assertNull($future->fresh()->ready_for_review_at);
        $this->assertSame(CommissionStatus::Pending, $future->fresh()->status);
    }

    public function test_daily_command_is_idempotent_and_does_not_mark_refunded_commissions(): void
    {
        $due = $this->commission('INV-DUE');
        $cancelled = $this->commission('INV-CANCELLED');
        $cancelled->forceFill(['status' => CommissionStatus::Cancelled])->save();
        $refunded = $this->commission('INV-REFUNDED');
        $refunded->transaction->update(['status' => 'refunded']);

        $this->artisan('commissions:mark-ready')->assertSuccessful();
        $firstMarkedAt = $due->fresh()->ready_for_review_at?->toIso8601String();
        $this->travel(1)->days();
        $this->artisan('commissions:mark-ready')->assertSuccessful();

        $this->assertSame($firstMarkedAt, $due->fresh()->ready_for_review_at?->toIso8601String());
        $this->assertNull($cancelled->fresh()->ready_for_review_at);
        $this->assertNull($refunded->fresh()->ready_for_review_at);
    }

    public function test_admin_approves_due_commission_and_audits_once(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $commission = $this->commission();

        app(ChangeCommissionStatus::class)->handle($admin, $commission, CommissionStatus::Approved);
        app(ChangeCommissionStatus::class)->handle($admin, $commission, CommissionStatus::Approved);

        $this->assertSame(CommissionStatus::Approved, $commission->fresh()->status);
        $this->assertNotNull($commission->fresh()->approved_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'subject_type' => $commission->getMorphClass(),
            'subject_id' => $commission->id,
            'action' => 'commission.approved',
        ]);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_admin_cannot_approve_before_waiting_period_ends(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $commission = $this->commission();
        $commission->forceFill(['approvable_at' => now()->addDay()])->save();

        try {
            app(ChangeCommissionStatus::class)->handle($admin, $commission, CommissionStatus::Approved);
            $this->fail('Komisi belum boleh disetujui.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(CommissionStatus::Pending, $commission->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_can_reject_pending_commission_before_due_date(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $commission = $this->commission();
        $commission->forceFill(['approvable_at' => now()->addDay()])->save();

        app(ChangeCommissionStatus::class)->handle($admin, $commission, CommissionStatus::Rejected);

        $this->assertSame(CommissionStatus::Rejected, $commission->fresh()->status);
        $this->assertNull($commission->fresh()->approved_at);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $commission->id, 'action' => 'commission.rejected']);
    }

    public function test_affiliate_cannot_change_commission_status_even_for_owned_record(): void
    {
        $commission = $this->commission();
        $affiliateUser = $commission->affiliate->user;

        $this->assertFalse(Gate::forUser($affiliateUser)->allows('approve', $commission));
        $this->assertFalse(Gate::forUser($affiliateUser)->allows('reject', $commission));

        $this->expectException(AuthorizationException::class);
        app(ChangeCommissionStatus::class)->handle($affiliateUser, $commission, CommissionStatus::Approved);
    }

    public function test_refunded_transaction_cannot_be_approved(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $commission = $this->commission();
        $commission->transaction->update(['status' => 'refunded']);

        try {
            app(ChangeCommissionStatus::class)->handle($admin, $commission, CommissionStatus::Approved);
            $this->fail('Komisi dari transaksi refund tidak boleh disetujui.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(CommissionStatus::Pending, $commission->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_cannot_reject_already_approved_commission(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $commission = $this->commission();
        app(ChangeCommissionStatus::class)->handle($admin, $commission, CommissionStatus::Approved);

        try {
            app(ChangeCommissionStatus::class)->handle($admin, $commission, CommissionStatus::Rejected);
            $this->fail('Komisi yang sudah disetujui tidak boleh ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(CommissionStatus::Approved, $commission->fresh()->status);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_status_change_rolls_back_when_audit_write_fails(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $commission = $this->commission();
        DB::unprepared("CREATE TEMP TRIGGER fail_commission_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");

        try {
            app(ChangeCommissionStatus::class)->handle($admin, $commission, CommissionStatus::Approved);
            $this->fail('Approval harus dibatalkan bila audit gagal.');
        } catch (QueryException) {
            $this->assertSame(CommissionStatus::Pending, $commission->fresh()->status);
            $this->assertNull($commission->fresh()->approved_at);
            $this->assertDatabaseCount('audit_logs', 0);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_commission_audit');
        }
    }

    public function test_only_admin_can_open_commission_review_pages(): void
    {
        $commission = $this->commission();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/admin/commissions')->assertOk();
        $this->get('/admin/commissions/'.$commission->id)->assertOk();

        $this->actingAs($commission->affiliate->user)->get('/admin/commissions')->assertForbidden();
        $this->get('/admin/commissions/'.$commission->id)->assertForbidden();
    }

    public function test_admin_approves_commission_through_review_page(): void
    {
        $commission = $this->commission();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewCommission::class, ['record' => $commission->id])
            ->assertActionVisible('approve')
            ->callAction('approve')
            ->assertHasNoActionErrors();

        $this->assertSame(CommissionStatus::Approved, $commission->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $commission->id, 'action' => 'commission.approved']);
    }

    public function test_review_page_hides_approve_before_due_date_but_allows_reject(): void
    {
        $commission = $this->commission();
        $commission->forceFill(['approvable_at' => now()->addDay()])->save();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewCommission::class, ['record' => $commission->id])
            ->assertActionHidden('approve')
            ->assertActionVisible('reject')
            ->callAction('reject')
            ->assertHasNoActionErrors();

        $this->assertSame(CommissionStatus::Rejected, $commission->fresh()->status);
    }

    public function test_review_page_allows_approval_at_exact_due_time(): void
    {
        $this->freezeSecond();
        $commission = $this->commission();
        $commission->forceFill(['approvable_at' => now()])->save();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewCommission::class, ['record' => $commission->id])
            ->assertActionVisible('approve');
    }

    public function test_admin_can_filter_commissions_by_status(): void
    {
        $pending = $this->commission('INV-PENDING');
        $approved = $this->commission('INV-APPROVED');
        $approved->forceFill(['status' => CommissionStatus::Approved])->save();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListCommissions::class)
            ->filterTable('status', CommissionStatus::Pending->value)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$approved]);
    }

    private function commission(string $externalRef = 'INV-001'): Commission
    {
        $affiliateUser = User::factory()->create(['role' => UserRole::Affiliate]);
        $affiliate = Affiliate::query()->create(['user_id' => $affiliateUser->id, 'status' => AffiliateStatus::Active]);
        $product = Product::query()->create([
            'code' => 'WEB-'.str_replace('INV-', '', $externalRef),
            'name' => 'Website Profesional',
            'type' => ProductType::Website,
            'is_active' => true,
        ]);

        $transaction = app(RecordTransaction::class)->handle([
            'external_ref' => $externalRef,
            'product_id' => $product->id,
            'referral_code' => $affiliate->referral_code,
            'source' => 'webhook',
            'purchase_type' => 'one_time',
            'sale_type' => 'new',
            'customer_name' => 'Pelanggan Contoh',
            'customer_email' => 'customer@example.test',
            'base_amount' => 1_000_000,
            'discount_amount' => 0,
            'tax_amount' => 110_000,
            'total_paid' => 1_110_000,
            'paid_at' => now()->subDays(20)->toIso8601String(),
        ]);

        return $transaction->commission;
    }
}
