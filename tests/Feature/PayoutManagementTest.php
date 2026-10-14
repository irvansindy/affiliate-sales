<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\CreatePayout;
use App\Actions\FailPayout;
use App\Actions\MarkPayoutPaid;
use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\Payouts\Pages\CreatePayout as CreatePayoutPage;
use App\Filament\Admin\Resources\Payouts\Pages\ListPayouts;
use App\Filament\Admin\Resources\Payouts\Pages\ViewPayout;
use App\Models\Affiliate;
use App\Models\Commission;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\PayoutOutcomeNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PayoutManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_requires_complete_bank_details_and_minimum_setting(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate();
        $commission = $this->commission($affiliate, 90_000);
        Setting::query()->create(['key' => 'min_payout', 'value' => '100000']);

        $this->assertThrows(fn () => app(CreatePayout::class)->handle($admin, $affiliate, [$commission->id]), ValidationException::class);
        $affiliate->update(['bank_name' => 'Bank A', 'bank_account_name' => 'Pemilik', 'bank_account_number' => '1234567890']);
        $this->assertThrows(fn () => app(CreatePayout::class)->handle($admin, $affiliate, [$commission->id]), ValidationException::class);
        $this->assertDatabaseCount('payouts', 0);
        $this->assertNull($commission->fresh()->payout_id);
    }

    public function test_creation_snapshots_bank_and_locks_only_eligible_commissions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(true);
        $commission = $this->commission($affiliate, 120_000);
        $other = $this->commission($this->affiliate(true), 120_000);

        $this->assertThrows(fn () => app(CreatePayout::class)->handle($admin, $affiliate, [$commission->id, $other->id]), ValidationException::class);
        $payout = app(CreatePayout::class)->handle($admin, $affiliate, [$commission->id]);
        $affiliate->update(['bank_account_number' => '9999999999']);

        $this->assertSame('1234567890', $payout->fresh()->bank_account_number);
        $this->assertSame($payout->id, $commission->fresh()->payout_id);
        $this->assertSame(CommissionStatus::Approved, $commission->fresh()->status);
        $this->assertThrows(fn () => app(CreatePayout::class)->handle($admin, $affiliate, [$commission->id]), ValidationException::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payout.created', 'subject_id' => $payout->id]);
    }

    public function test_failed_payout_releases_commissions_and_can_be_retried(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(true);
        $commission = $this->commission($affiliate, 120_000);
        $payout = app(CreatePayout::class)->handle($admin, $affiliate, [$commission->id]);

        app(FailPayout::class)->handle($admin, $payout);

        $this->assertSame(CommissionStatus::Approved, $commission->fresh()->status);
        $this->assertNull($commission->fresh()->payout_id);
        $this->assertThrows(fn () => app(FailPayout::class)->handle($admin, $payout), AuthorizationException::class);
        $retry = app(CreatePayout::class)->handle($admin, $affiliate, [$commission->id]);
        $this->assertNotSame($payout->id, $retry->id);
        Notification::assertSentTo($affiliate->user, PayoutOutcomeNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payout.failed', 'subject_id' => $payout->id]);
    }

    public function test_paid_payout_requires_proof_and_is_terminal(): void
    {
        Storage::fake('local');
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(true);
        $commission = $this->commission($affiliate, 120_000);
        $payout = app(CreatePayout::class)->handle($admin, $affiliate, [$commission->id]);

        $this->assertSame(CommissionStatus::Approved, $commission->fresh()->status);

        $paid = app(MarkPayoutPaid::class)->handle($admin, $payout, UploadedFile::fake()->image('bukti.jpg'));
        $this->assertSame(CommissionStatus::Paid, $commission->fresh()->status);
        $this->assertNotNull($paid->paid_at);
        Storage::disk('local')->assertExists($paid->proof_path);
        $this->assertThrows(fn () => app(MarkPayoutPaid::class)->handle($admin, $paid, UploadedFile::fake()->image('lagi.jpg')), AuthorizationException::class);
        $this->assertThrows(fn () => app(FailPayout::class)->handle($admin, $paid), AuthorizationException::class);
        Notification::assertSentTo($affiliate->user, PayoutOutcomeNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payout.paid', 'subject_id' => $payout->id]);
    }

    public function test_affiliate_cannot_create_or_finalize_payout(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(true);
        $commission = $this->commission($affiliate, 120_000);
        $this->assertThrows(fn () => app(CreatePayout::class)->handle($affiliate->user, $affiliate, [$commission->id]), AuthorizationException::class);
        $payout = app(CreatePayout::class)->handle($admin, $affiliate, [$commission->id]);
        $this->assertThrows(fn () => app(FailPayout::class)->handle($affiliate->user, $payout), AuthorizationException::class);
    }

    public function test_admin_can_create_and_view_payout_in_resource(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(true);
        $commission = $this->commission($affiliate, 120_000);
        $this->actingAs($admin);

        Livewire::test(ListPayouts::class)->assertSuccessful();
        Livewire::test(CreatePayoutPage::class)
            ->fillForm(['affiliate_id' => $affiliate->id, 'commissions' => [$commission->id]])
            ->call('create')->assertHasNoFormErrors();

        $payout = $commission->fresh()->payout;
        $this->assertNotNull($payout);
        Livewire::test(ViewPayout::class, ['record' => $payout->id])->assertSuccessful()
            ->assertSee('****7890')->assertDontSee('1234567890');
    }

    public function test_stored_private_proof_can_finalize_payout_and_has_temporary_url(): void
    {
        Storage::fake('local');
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(true);
        $commission = $this->commission($affiliate, 120_000);
        $payout = app(CreatePayout::class)->handle($admin, $affiliate, [$commission->id]);
        $path = UploadedFile::fake()->image('bukti.jpg')->store('payout-proofs', 'local');

        $paid = app(MarkPayoutPaid::class)->handle($admin, $payout, $path);

        $this->assertSame($path, $paid->proof_path);
        $this->assertSame(CommissionStatus::Paid, $commission->fresh()->status);
        $this->assertStringContainsString('expiration=', Storage::disk('local')->temporaryUrl($path, now()->addMinutes(10)));
    }

    public function test_suspended_affiliate_can_receive_approved_commission(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(true);
        $affiliate->update(['status' => AffiliateStatus::Suspended]);
        $commission = $this->commission($affiliate, 120_000);

        $payout = app(CreatePayout::class)->handle($admin, $affiliate, [$commission->id]);

        $this->assertSame($affiliate->id, $payout->affiliate_id);
    }

    public function test_cancelled_commission_cannot_be_paid_or_reapproved_by_failed_payout(): void
    {
        Storage::fake('local');
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate(true);
        $commission = $this->commission($affiliate, 120_000);
        $payout = app(CreatePayout::class)->handle($admin, $affiliate, [$commission->id]);
        $commission->forceFill(['status' => CommissionStatus::Cancelled])->save();

        $this->assertThrows(fn () => app(MarkPayoutPaid::class)->handle($admin, $payout, UploadedFile::fake()->image('bukti.jpg')), ValidationException::class);
        app(FailPayout::class)->handle($admin, $payout);

        $this->assertSame(CommissionStatus::Cancelled, $commission->fresh()->status);
        $this->assertNull($commission->fresh()->payout_id);
    }

    private function affiliate(bool $withBank = false): Affiliate
    {
        return Affiliate::query()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Affiliate])->id,
            'status' => AffiliateStatus::Active,
            'bank_name' => $withBank ? 'Bank A' : null,
            'bank_account_name' => $withBank ? 'Pemilik' : null,
            'bank_account_number' => $withBank ? '1234567890' : null,
        ]);
    }

    private function commission(Affiliate $affiliate, int $amount): Commission
    {
        $product = Product::query()->create(['code' => 'WEB-'.uniqid(), 'name' => 'Website', 'type' => ProductType::Website, 'is_active' => true]);
        $transaction = Transaction::query()->create([
            'external_ref' => 'INV-'.uniqid(), 'product_id' => $product->id, 'affiliate_id' => $affiliate->id,
            'source' => 'manual', 'purchase_type' => 'one_time', 'sale_type' => 'new',
            'customer_name' => 'Pelanggan', 'customer_email' => 'customer@example.test',
            'base_amount' => $amount * 10, 'total_paid' => $amount * 10, 'paid_at' => now(),
        ]);
        $commission = Commission::query()->create([
            'transaction_id' => $transaction->id, 'affiliate_id' => $affiliate->id,
            'base_amount' => $amount * 10, 'rate' => '10.00', 'amount' => $amount,
        ]);
        $commission->forceFill(['status' => CommissionStatus::Approved])->save();

        return $commission;
    }
}
