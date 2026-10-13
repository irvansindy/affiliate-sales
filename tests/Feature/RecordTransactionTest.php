<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\RecordTransaction;
use App\Enums\AffiliateStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class RecordTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_sale_attributes_affiliate_and_creates_commission_from_base_amount(): void
    {
        $affiliate = $this->affiliate();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $transaction = app(RecordTransaction::class)->handle($this->payload([
            'source' => 'manual',
            'referral_code' => $affiliate->referral_code,
            'base_amount' => 5_000_000,
            'discount_amount' => 500_000,
            'tax_amount' => 495_000,
            'total_paid' => 4_995_000,
        ]), $admin);

        $this->assertSame($affiliate->id, $transaction->affiliate_id);
        $this->assertSame('manual', $transaction->source->value);
        $this->assertSame('paid', $transaction->status->value);
        $this->assertFalse($transaction->needs_review);
        $this->assertSame(500_000, (int) $transaction->commission->amount);
        $this->assertSame(1, Commission::query()->count());
    }

    public function test_missing_referral_records_sale_without_review_or_commission(): void
    {
        $transaction = app(RecordTransaction::class)->handle($this->payload());

        $this->assertNull($transaction->affiliate_id);
        $this->assertFalse($transaction->needs_review);
        $this->assertNull($transaction->review_note);
        $this->assertSame(0, Commission::query()->count());
    }

    public function test_unknown_referral_records_sale_for_review_without_commission(): void
    {
        $transaction = app(RecordTransaction::class)->handle($this->payload([
            'referral_code' => 'UNKNOWN',
        ]));

        $this->assertNull($transaction->affiliate_id);
        $this->assertTrue($transaction->needs_review);
        $this->assertNotNull($transaction->review_note);
        $this->assertSame(0, Commission::query()->count());
    }

    public function test_inactive_referrals_record_sales_for_review_without_commission(): void
    {
        foreach ([AffiliateStatus::Pending, AffiliateStatus::Suspended, AffiliateStatus::Rejected] as $status) {
            $affiliate = $this->affiliate($status);
            $transaction = app(RecordTransaction::class)->handle($this->payload([
                'external_ref' => 'INV-'.$status->value,
                'referral_code' => $affiliate->referral_code,
            ]));

            $this->assertNull($transaction->affiliate_id);
            $this->assertTrue($transaction->needs_review);
        }

        $this->assertSame(0, Commission::query()->count());
    }

    public function test_self_referral_records_sale_for_review_without_commission(): void
    {
        $affiliate = $this->affiliate(email: 'owner@example.com');
        $transaction = app(RecordTransaction::class)->handle($this->payload([
            'referral_code' => $affiliate->referral_code,
            'customer_email' => 'OWNER@example.com',
        ]));

        $this->assertNull($transaction->affiliate_id);
        $this->assertTrue($transaction->needs_review);
        $this->assertSame(0, Commission::query()->count());
    }

    public function test_renewal_and_plan_change_are_recorded_without_commission(): void
    {
        $affiliate = $this->affiliate();

        foreach (['renewal', 'plan_change'] as $saleType) {
            $transaction = app(RecordTransaction::class)->handle($this->payload([
                'external_ref' => 'INV-'.$saleType,
                'referral_code' => $affiliate->referral_code,
                'purchase_type' => 'subscription',
                'sale_type' => $saleType,
                'subscription_plan' => 'yearly',
                'subscription_cycle' => 2,
            ]));

            $this->assertSame($affiliate->id, $transaction->affiliate_id);
            $this->assertNull($transaction->commission);
        }

        $this->assertSame(0, Commission::query()->count());
    }

    public function test_initial_subscription_creates_one_commission(): void
    {
        $affiliate = $this->affiliate();
        $transaction = app(RecordTransaction::class)->handle($this->payload([
            'referral_code' => $affiliate->referral_code,
            'purchase_type' => 'subscription',
            'subscription_plan' => 'yearly',
            'subscription_cycle' => 1,
        ]));

        $this->assertSame($affiliate->id, $transaction->affiliate_id);
        $this->assertSame(500_000, (int) $transaction->commission->amount);
    }

    public function test_invalid_amounts_do_not_persist_transaction_or_commission(): void
    {
        try {
            app(RecordTransaction::class)->handle($this->payload([
                'discount_amount' => 500_000,
                'total_paid' => 5_550_000,
            ]));
            $this->fail('Nominal tidak sesuai harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('total_paid', $exception->errors());
        }

        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, Commission::query()->count());
    }

    public function test_duplicate_external_reference_is_rejected(): void
    {
        app(RecordTransaction::class)->handle($this->payload());

        try {
            app(RecordTransaction::class)->handle($this->payload());
            $this->fail('Referensi transaksi yang sama harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('external_ref', $exception->errors());
        }

        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_webhook_source_uses_the_same_recording_action(): void
    {
        $transaction = app(RecordTransaction::class)->handle($this->payload(['source' => 'webhook']));

        $this->assertSame('webhook', $transaction->source->value);
        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_manual_source_requires_admin_and_records_an_audit_entry(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = User::factory()->create(['role' => UserRole::Affiliate]);
        $input = $this->payload(['source' => 'manual']);

        foreach ([null, $affiliate] as $actor) {
            try {
                app(RecordTransaction::class)->handle($input, $actor);
                $this->fail('Input manual harus memerlukan admin.');
            } catch (AuthorizationException) {
                $this->assertSame(0, Transaction::query()->count());
            }
        }

        $transaction = app(RecordTransaction::class)->handle($input, $admin);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'subject_type' => $transaction->getMorphClass(),
            'subject_id' => $transaction->id,
            'action' => 'transaction.recorded',
        ]);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_subscription_requires_plan_and_cycle(): void
    {
        $this->assertInvalid(['purchase_type' => 'subscription'], ['subscription_plan', 'subscription_cycle']);
    }

    public function test_one_time_purchase_cannot_be_a_renewal(): void
    {
        $this->assertInvalid(['sale_type' => 'renewal'], ['sale_type']);
    }

    public function test_first_subscription_payment_must_be_cycle_one(): void
    {
        $this->assertInvalid([
            'purchase_type' => 'subscription',
            'subscription_plan' => 'yearly',
            'subscription_cycle' => 2,
        ], ['subscription_cycle']);
    }

    public function test_discount_cannot_exceed_base_amount(): void
    {
        $this->assertInvalid(['discount_amount' => 5_000_001], ['discount_amount']);
    }

    public function test_amount_too_large_for_integer_commission_math_is_rejected(): void
    {
        $this->assertInvalid([
            'base_amount' => 9_223_372_036_854_776,
            'total_paid' => 9_223_372_037_404_776,
        ], ['base_amount']);
    }

    public function test_transaction_rolls_back_when_commission_creation_fails(): void
    {
        $affiliate = $this->affiliate();
        Setting::query()->updateOrCreate(['key' => 'default_commission_rate'], ['value' => '-1']);

        try {
            app(RecordTransaction::class)->handle($this->payload([
                'referral_code' => $affiliate->referral_code,
            ]));
            $this->fail('Komisi yang gagal harus membatalkan transaksi.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, Transaction::query()->count());
            $this->assertSame(0, Commission::query()->count());
        }
    }

    private function affiliate(AffiliateStatus $status = AffiliateStatus::Active, string $email = 'affiliate@example.com'): Affiliate
    {
        $user = User::factory()->create([
            'email' => $email === 'affiliate@example.com' ? fake()->unique()->safeEmail() : $email,
            'role' => UserRole::Affiliate,
        ]);

        return Affiliate::query()->create(['user_id' => $user->id, 'status' => $status]);
    }

    /** @param array<string, mixed> $overrides
     * @param  array<int, string>  $expectedFields
     */
    private function assertInvalid(array $overrides, array $expectedFields): void
    {
        try {
            app(RecordTransaction::class)->handle($this->payload($overrides));
            $this->fail('Input tidak valid harus ditolak.');
        } catch (ValidationException $exception) {
            foreach ($expectedFields as $field) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertSame(0, Transaction::query()->count());
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        $product = Product::query()->firstOrCreate(
            ['code' => 'WEBSITE_PRO'],
            ['name' => 'Website Pro', 'type' => ProductType::Website, 'is_active' => true],
        );

        return array_replace([
            'external_ref' => 'INV-2026-001',
            'product_id' => $product->id,
            'referral_code' => null,
            'source' => 'webhook',
            'purchase_type' => 'one_time',
            'sale_type' => 'new',
            'subscription_plan' => null,
            'subscription_cycle' => null,
            'customer_name' => 'PT Contoh',
            'customer_email' => 'pelanggan@example.com',
            'base_amount' => 5_000_000,
            'discount_amount' => 0,
            'tax_amount' => 550_000,
            'total_paid' => 5_550_000,
            'paid_at' => '2026-10-12 14:30:00',
        ], $overrides);
    }
}
