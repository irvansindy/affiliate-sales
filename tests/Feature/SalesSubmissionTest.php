<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ReviewSalesSubmission;
use App\Actions\SubmitSalesSubmission;
use App\Actions\UpdateCommissionRate;
use App\Enums\AffiliateStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\SalesSubmissions\Pages\ViewSalesSubmission;
use App\Filament\Affiliate\Resources\SalesSubmissions\Pages\CreateSalesSubmission;
use App\Models\Affiliate;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SalesSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_submission_requires_private_payment_proof_and_creates_no_sale_or_commission(): void
    {
        Storage::fake('local');
        $affiliate = $this->affiliate();
        $product = $this->product();
        Storage::disk('local')->put('sales-proofs/'.$affiliate->id.'/proof.pdf', '%PDF-1.4 payment');

        $submission = app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($product));

        $this->assertSame('pending', $submission->status->value);
        $this->assertSame($affiliate->id, $submission->affiliate_id);
        $this->assertSame('sales-proofs/'.$affiliate->id.'/proof.pdf', $submission->proof_path);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('commissions', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sales_submission.submitted', 'subject_id' => $submission->id]);

        $this->assertThrows(fn () => app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($product, ['proof_path' => null])), ValidationException::class);
        $this->assertThrows(fn () => app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($product, ['proof_path' => 'sales-proofs/'.$affiliate->id.'/../other/proof.pdf'])), ValidationException::class);
        $this->assertDatabaseCount('sales_submissions', 1);
    }

    public function test_only_active_affiliate_can_submit_and_cannot_claim_an_inactive_product(): void
    {
        Storage::fake('local');
        $affiliate = $this->affiliate(AffiliateStatus::Suspended);
        Storage::disk('local')->put('sales-proofs/'.$affiliate->id.'/proof.pdf', '%PDF-1.4 payment');
        $product = $this->product();

        $this->assertThrows(fn () => app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($product)), AuthorizationException::class);
        $affiliate->update(['status' => AffiliateStatus::Active]);
        $product->update(['is_active' => false]);
        $this->assertThrows(fn () => app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($product)), ValidationException::class);
        $this->assertDatabaseCount('sales_submissions', 0);
    }

    public function test_admin_approval_creates_one_paid_transaction_and_snapshotted_commission(): void
    {
        Storage::fake('local');
        Setting::query()->create(['key' => 'default_commission_rate', 'value' => '10']);
        $affiliate = $this->affiliate();
        Storage::disk('local')->put('sales-proofs/'.$affiliate->id.'/proof.pdf', '%PDF-1.4 payment');
        $submission = app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($this->product()));
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $transaction = app(ReviewSalesSubmission::class)->approve($admin, $submission, $this->payment());

        $this->assertSame($affiliate->id, $transaction->affiliate_id);
        $this->assertSame('paid', $transaction->status->value);
        $this->assertSame(5_000_000, $transaction->base_amount);
        $this->assertSame(500_000, $transaction->commission->amount);
        $this->assertSame('10.00', $transaction->commission->rate);
        $this->assertSame($transaction->id, $submission->fresh()->transaction_id);
        $this->assertSame('approved', $submission->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sales_submission.approved', 'subject_id' => $submission->id]);

        $this->assertThrows(fn () => app(ReviewSalesSubmission::class)->approve($admin, $submission, $this->payment()), ValidationException::class);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('commissions', 1);
    }

    public function test_rejection_requires_reason_and_never_creates_commission(): void
    {
        Storage::fake('local');
        $affiliate = $this->affiliate();
        Storage::disk('local')->put('sales-proofs/'.$affiliate->id.'/proof.pdf', '%PDF-1.4 payment');
        $submission = app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($this->product()));
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertThrows(fn () => app(ReviewSalesSubmission::class)->reject($admin, $submission, ''), ValidationException::class);
        app(ReviewSalesSubmission::class)->reject($admin, $submission, 'Bukti tidak cocok dengan rekening penerima.');

        $this->assertSame('rejected', $submission->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sales_submission.rejected', 'subject_id' => $submission->id]);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('commissions', 0);
    }

    public function test_admin_must_verify_unique_reference_and_payment_amounts(): void
    {
        Storage::fake('local');
        $affiliate = $this->affiliate();
        Storage::disk('local')->put('sales-proofs/'.$affiliate->id.'/proof.pdf', '%PDF-1.4 payment');
        $product = $this->product();
        $first = app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($product));
        $second = app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($product));
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        app(ReviewSalesSubmission::class)->approve($admin, $first, $this->payment());

        $this->assertThrows(fn () => app(ReviewSalesSubmission::class)->approve($admin, $second, $this->payment()), ValidationException::class);
        $this->assertThrows(fn () => app(ReviewSalesSubmission::class)->approve($admin, $second, $this->payment(['external_ref' => 'INV-2', 'total_paid' => 1])), ValidationException::class);
        $this->assertSame('pending', $second->fresh()->status->value);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_affiliate_cannot_approve_own_submission(): void
    {
        Storage::fake('local');
        $affiliate = $this->affiliate();
        Storage::disk('local')->put('sales-proofs/'.$affiliate->id.'/proof.pdf', '%PDF-1.4 payment');
        $submission = app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($this->product()));

        $this->assertThrows(fn () => app(ReviewSalesSubmission::class)->approve($affiliate->user, $submission, $this->payment()), AuthorizationException::class);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_panel_routes_enforce_role_and_submission_ownership(): void
    {
        Storage::fake('local');
        $firstAffiliate = $this->affiliate();
        Storage::disk('local')->put('sales-proofs/'.$firstAffiliate->id.'/proof.pdf', '%PDF-1.4 payment');
        $first = app(SubmitSalesSubmission::class)->handle($firstAffiliate->user, $this->claim($this->product()));
        $secondUser = User::factory()->create(['role' => UserRole::Affiliate]);
        Affiliate::query()->create(['user_id' => $secondUser->id, 'referral_code' => 'AFF456', 'status' => AffiliateStatus::Active]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($firstAffiliate->user)->get('/affiliate/sales-submissions/create')->assertOk();
        $this->actingAs($secondUser)->get('/affiliate/sales-submissions/'.$first->id)->assertNotFound();
        $this->actingAs($admin)->get('/admin/sales-submissions/'.$first->id)->assertOk();
        $this->actingAs($admin)->get('/admin/commission-settings')->assertOk();
        $this->actingAs($firstAffiliate->user)->get('/admin/sales-submissions/'.$first->id)->assertForbidden();
        $this->actingAs($firstAffiliate->user)->get('/admin/commission-settings')->assertForbidden();
    }

    public function test_admin_can_change_global_rate_only_for_future_commissions(): void
    {
        Storage::fake('local');
        $affiliate = $this->affiliate();
        Storage::disk('local')->put('sales-proofs/'.$affiliate->id.'/proof.pdf', '%PDF-1.4 payment');
        $product = $this->product();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $first = app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($product));
        $firstTransaction = app(ReviewSalesSubmission::class)->approve($admin, $first, $this->payment());

        app(UpdateCommissionRate::class)->handle($admin, '12.5');
        $second = app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($product));
        $secondTransaction = app(ReviewSalesSubmission::class)->approve($admin, $second, $this->payment(['external_ref' => 'INV-2']));

        $this->assertSame('10.00', $firstTransaction->commission->fresh()->rate);
        $this->assertSame(500_000, $firstTransaction->commission->fresh()->amount);
        $this->assertSame('12.50', $secondTransaction->commission->rate);
        $this->assertSame(625_000, $secondTransaction->commission->amount);
        $this->assertDatabaseHas('audit_logs', ['action' => 'setting.commission_rate.updated']);
        $this->assertThrows(fn () => app(UpdateCommissionRate::class)->handle($affiliate->user, '15'), AuthorizationException::class);
        $this->assertThrows(fn () => app(UpdateCommissionRate::class)->handle($admin, '101'), ValidationException::class);
    }

    public function test_subscription_claim_requires_admin_plan_and_proof_still_present(): void
    {
        Storage::fake('local');
        $affiliate = $this->affiliate();
        $path = 'sales-proofs/'.$affiliate->id.'/proof.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 payment');
        $submission = app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($this->product(), ['purchase_type' => 'subscription']));
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertThrows(fn () => app(ReviewSalesSubmission::class)->approve($admin, $submission, $this->payment()), ValidationException::class);
        Storage::disk('local')->delete($path);
        $this->assertThrows(fn () => app(ReviewSalesSubmission::class)->approve($admin, $submission, $this->payment(['subscription_plan' => 'Bulanan'])), ValidationException::class);
        $this->assertDatabaseCount('transactions', 0);

        Storage::disk('local')->put($path, '%PDF-1.4 payment');
        $transaction = app(ReviewSalesSubmission::class)->approve($admin, $submission, $this->payment(['subscription_plan' => 'Bulanan']));
        $this->assertSame(1, $transaction->subscription_cycle);
        $this->assertSame('subscription', $transaction->purchase_type->value);
        $this->assertSame(500_000, $transaction->commission->amount);
    }

    public function test_admin_can_approve_from_submission_page(): void
    {
        Storage::fake('local');
        $affiliate = $this->affiliate();
        Storage::disk('local')->put('sales-proofs/'.$affiliate->id.'/proof.pdf', '%PDF-1.4 payment');
        $submission = app(SubmitSalesSubmission::class)->handle($affiliate->user, $this->claim($this->product()));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewSalesSubmission::class, ['record' => $submission->id])
            ->assertActionVisible('approve')
            ->callAction('approve', data: $this->payment())
            ->assertHasNoActionErrors();

        $this->assertSame('approved', $submission->fresh()->status->value);
        $this->assertDatabaseCount('commissions', 1);
    }

    public function test_affiliate_form_requires_proof_and_submits_without_money_fields(): void
    {
        Storage::fake('local');
        $affiliate = $this->affiliate();
        $product = $this->product();
        $this->actingAs($affiliate->user);
        Filament::setCurrentPanel(Filament::getPanel('affiliate'));
        $claim = $this->claim($product);
        unset($claim['proof_path']);

        Livewire::test(CreateSalesSubmission::class)->fillForm($claim)
            ->call('create')->assertHasFormErrors(['proof_path' => 'required']);

        Livewire::test(CreateSalesSubmission::class)->fillForm([
            ...$claim,
            'proof_path' => UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4 payment'),
        ])->call('create')->assertHasNoFormErrors();

        $this->assertDatabaseCount('sales_submissions', 1);
        $this->assertDatabaseCount('transactions', 0);
    }

    private function affiliate(AffiliateStatus $status = AffiliateStatus::Active): Affiliate
    {
        return Affiliate::query()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Affiliate])->id,
            'referral_code' => 'AFF123',
            'status' => $status,
        ]);
    }

    private function product(): Product
    {
        return Product::query()->create(['code' => 'WEB-PRO', 'name' => 'Website Pro', 'type' => ProductType::Website, 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    private function claim(Product $product, array $overrides = []): array
    {
        return array_replace([
            'product_id' => $product->id,
            'purchase_type' => 'one_time',
            'customer_name' => 'Pelanggan A',
            'customer_email' => 'buyer@example.test',
            'claimed_ref' => 'TRANSFER-123',
            'proof_path' => 'sales-proofs/'.Affiliate::query()->firstOrFail()->id.'/proof.pdf',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function payment(array $overrides = []): array
    {
        return array_replace([
            'external_ref' => 'INV-1',
            'base_amount' => 5_000_000,
            'discount_amount' => 500_000,
            'tax_amount' => 495_000,
            'total_paid' => 4_995_000,
            'paid_at' => now()->toDateTimeString(),
        ], $overrides);
    }
}
