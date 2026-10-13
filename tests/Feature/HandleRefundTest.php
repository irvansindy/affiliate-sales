<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\HandleRefund;
use App\Actions\RecordTransaction;
use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HandleRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_refund_cancels_pending_commission_and_writes_system_audit(): void
    {
        $transaction = $this->saleWithCommission();

        app(HandleRefund::class)->handle($transaction, 1_000_000, '2026-10-20T09:00:00+07:00');

        $this->assertSame('refunded', $transaction->fresh()->status->value);
        $this->assertSame(1_000_000, (int) $transaction->fresh()->refund_amount);
        $this->assertSame('cancelled', $transaction->commission->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'subject_type' => $transaction->getMorphClass(),
            'subject_id' => $transaction->id,
            'action' => 'transaction.refunded',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'subject_type' => $transaction->commission->getMorphClass(),
            'subject_id' => $transaction->commission->id,
            'action' => 'commission.cancelled_by_refund',
        ]);
    }

    public function test_refund_cancels_approved_commission(): void
    {
        $transaction = $this->saleWithCommission();
        $transaction->commission->forceFill(['status' => CommissionStatus::Approved])->save();

        app(HandleRefund::class)->handle($transaction, 5_550_000, '2026-10-20T09:00:00+07:00');

        $this->assertSame('cancelled', $transaction->commission->fresh()->status->value);
    }

    public function test_refund_keeps_paid_commission_and_audits_no_clawback(): void
    {
        $transaction = $this->saleWithCommission();
        $transaction->commission->forceFill(['status' => CommissionStatus::Paid])->save();

        app(HandleRefund::class)->handle($transaction, 5_550_000, '2026-10-20T09:00:00+07:00');

        $this->assertSame('refunded', $transaction->fresh()->status->value);
        $this->assertSame('paid', $transaction->commission->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => $transaction->commission->getMorphClass(),
            'subject_id' => $transaction->commission->id,
            'action' => 'commission.refund_after_paid',
        ]);
    }

    public function test_repeated_refund_does_not_change_amount_or_duplicate_audit(): void
    {
        $transaction = $this->saleWithCommission();

        app(HandleRefund::class)->handle($transaction, 1_000_000, '2026-10-20T09:00:00+07:00');
        app(HandleRefund::class)->handle($transaction, 2_000_000, '2026-10-21T09:00:00+07:00');

        $this->assertSame(1_000_000, (int) $transaction->fresh()->refund_amount);
        $this->assertSame(2, AuditLog::query()->count());
    }

    public function test_refund_larger_than_payment_is_rejected_without_changes(): void
    {
        $transaction = $this->saleWithCommission();

        try {
            app(HandleRefund::class)->handle($transaction, 5_550_001, '2026-10-20T09:00:00+07:00');
            $this->fail('Refund lebih besar daripada pembayaran harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund_amount', $exception->errors());
        }

        $this->assertSame('paid', $transaction->fresh()->status->value);
        $this->assertSame('pending', $transaction->commission->fresh()->status->value);
        $this->assertSame(0, AuditLog::query()->count());
    }

    private function saleWithCommission(): Transaction
    {
        $user = User::factory()->create(['role' => UserRole::Affiliate]);
        $affiliate = Affiliate::query()->create(['user_id' => $user->id, 'status' => AffiliateStatus::Active]);
        $product = Product::query()->create([
            'code' => 'WEB-PRO',
            'name' => 'Website Pro',
            'type' => ProductType::Website,
            'is_active' => true,
        ]);

        return app(RecordTransaction::class)->handle([
            'external_ref' => 'INV-001',
            'product_id' => $product->id,
            'referral_code' => $affiliate->referral_code,
            'source' => 'webhook',
            'purchase_type' => 'one_time',
            'sale_type' => 'new',
            'customer_name' => 'PT Contoh',
            'customer_email' => 'pelanggan@example.com',
            'base_amount' => 5_000_000,
            'discount_amount' => 0,
            'tax_amount' => 550_000,
            'total_paid' => 5_550_000,
            'paid_at' => '2026-10-12T14:30:00+07:00',
        ]);
    }
}
