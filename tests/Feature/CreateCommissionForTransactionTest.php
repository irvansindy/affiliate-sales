<?php

// declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\CreateCommissionForTransaction;
use App\Enums\AffiliateStatus;
use App\Enums\ProductType;
use App\Enums\PurchaseType;
use App\Enums\SaleType;
use App\Enums\TransactionSource;
use App\Enums\TransactionStatus;
use App\Models\Affiliate;
use App\Models\Commission;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateCommissionForTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function affiliate(AffiliateStatus $status = AffiliateStatus::Active, string $email = 'affiliate@example.com'): Affiliate
    {
        $user = User::factory()->create(['email' => $email]);

        return Affiliate::create(['user_id' => $user->id, 'status' => $status]);
    }

    private function transaction(?Affiliate $affiliate, array $overrides = []): Transaction
    {
        $product = Product::firstOrCreate(
            ['code' => 'WEBSITE_PRO'],
            ['name' => 'Website Pro', 'type' => ProductType::Website],
        );

        return Transaction::create(array_merge([
            'external_ref' => (string) Str::uuid(),
            'product_id' => $product->id,
            'affiliate_id' => $affiliate?->id,
            'source' => TransactionSource::Webhook,
            'purchase_type' => PurchaseType::OneTime,
            'sale_type' => SaleType::NewSale,
            'customer_name' => 'PT Contoh',
            'customer_email' => 'pelanggan@contoh.co.id',
            'base_amount' => 5_000_000,
            'discount_amount' => 0,
            'tax_amount' => 550_000,
            'total_paid' => 5_550_000,
            'status' => TransactionStatus::Paid,
            'paid_at' => '2026-10-12 14:30:00',
        ], $overrides));
    }

    private function handle(Transaction $transaction): ?Commission
    {
        return app(CreateCommissionForTransaction::class)->handle($transaction);
    }

    public function test_one_time_sale_earns_ten_percent_of_base_amount(): void
    {
        $affiliate = $this->affiliate();
        $transaction = $this->transaction($affiliate);

        $commission = $this->handle($transaction);

        $this->assertNotNull($commission);
        $this->assertEquals(500_000, $commission->amount);
        $this->assertEquals(5_000_000, $commission->base_amount);
        $this->assertEquals($affiliate->id, $commission->affiliate_id);
        $this->assertEquals($transaction->id, $commission->transaction_id);
    }

    public function test_initial_subscription_sale_excludes_vat_and_ignores_discount(): void
    {
        $transaction = $this->transaction($this->affiliate(), [
            'purchase_type' => PurchaseType::Subscription,
            'subscription_plan' => 'yearly',
            'subscription_cycle' => 1,
            'base_amount' => 5_000_000,
            'discount_amount' => 500_000,
            'tax_amount' => 495_000,
            'total_paid' => 4_995_000,
        ]);

        $this->assertEquals(500_000, $this->handle($transaction)->amount);
    }

    public function test_renewal_earns_nothing(): void
    {
        $transaction = $this->transaction($this->affiliate(), [
            'purchase_type' => PurchaseType::Subscription,
            'sale_type' => SaleType::Renewal,
            'subscription_cycle' => 2,
        ]);

        $this->assertNull($this->handle($transaction));
        $this->assertSame(0, Commission::count());
    }

    public function test_plan_change_earns_nothing(): void
    {
        $transaction = $this->transaction($this->affiliate(), [
            'purchase_type' => PurchaseType::Subscription,
            'sale_type' => SaleType::PlanChange,
        ]);

        $this->assertNull($this->handle($transaction));
    }

    public function test_transaction_without_affiliate_earns_nothing(): void
    {
        $this->assertNull($this->handle($this->transaction(null)));
        $this->assertSame(0, Commission::count());
    }

    public function test_non_active_affiliate_earns_nothing(): void
    {
        foreach ([AffiliateStatus::Pending, AffiliateStatus::Suspended, AffiliateStatus::Rejected] as $status) {
            $affiliate = $this->affiliate($status, "{$status->value}@example.com");

            $this->assertNull($this->handle($this->transaction($affiliate)), "Status {$status->value} tidak boleh berkomisi");
        }

        $this->assertSame(0, Commission::count());
    }

    public function test_self_referral_earns_nothing(): void
    {
        $affiliate = $this->affiliate(AffiliateStatus::Active, 'owner@example.com');
        $transaction = $this->transaction($affiliate, ['customer_email' => 'OWNER@example.com']);

        $this->assertNull($this->handle($transaction));
    }

    public function test_refunded_or_cancelled_transaction_earns_nothing(): void
    {
        $affiliate = $this->affiliate();

        foreach ([TransactionStatus::Refunded, TransactionStatus::Cancelled] as $status) {
            $this->assertNull($this->handle($this->transaction($affiliate, ['status' => $status])));
        }
    }

    public function test_creation_is_idempotent(): void
    {
        $transaction = $this->transaction($this->affiliate());

        $first = $this->handle($transaction);
        $second = $this->handle($transaction);

        $this->assertSame(1, Commission::count());
        $this->assertEquals($first->id, $second->id);
    }

    public function test_new_commission_is_pending_and_approvable_after_the_delay(): void
    {
        $commission = $this->handle($this->transaction($this->affiliate()));

        $this->assertSame('pending', $commission->status->value);
        $this->assertSame('2026-10-26 14:30:00', $commission->approvable_at->format('Y-m-d H:i:s')); // +14 hari
        $this->assertNull($commission->approved_at);
    }

    public function test_rate_is_snapshotted_per_commission(): void
    {
        $affiliate = $this->affiliate();

        $old = $this->handle($this->transaction($affiliate));

        Setting::updateOrCreate(['key' => 'default_commission_rate'], ['value' => '15']);

        $new = $this->handle($this->transaction($affiliate));

        $this->assertEquals('10.00', $old->fresh()->rate);
        $this->assertEquals(500_000, $old->fresh()->amount);
        $this->assertEquals('15.00', $new->rate);
        $this->assertEquals(750_000, $new->amount);
    }

    public function test_approval_delay_comes_from_settings(): void
    {
        Setting::updateOrCreate(['key' => 'approval_delay_days'], ['value' => '7']);

        $commission = $this->handle($this->transaction($this->affiliate()));

        $this->assertSame('2026-10-19 14:30:00', $commission->approvable_at->format('Y-m-d H:i:s'));
    }
}
