<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\RecordTransaction;
use App\Enums\AffiliateStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Admin\Resources\Transactions\Pages\ListTransactions;
use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ManualTransactionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_transaction_pages_but_affiliate_cannot(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = User::factory()->create(['role' => UserRole::Affiliate]);

        $this->actingAs($admin)->get('/admin/transactions')->assertOk();
        $this->get('/admin/transactions/create')->assertOk();

        $this->actingAs($affiliate)->get('/admin/transactions')->assertForbidden();
        $this->get('/admin/transactions/create')->assertForbidden();
    }

    public function test_policy_allows_only_admin_to_create_transactions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = User::factory()->create(['role' => UserRole::Affiliate]);

        $this->assertTrue(Gate::forUser($admin)->allows('create', Transaction::class));
        $this->assertFalse(Gate::forUser($affiliate)->allows('create', Transaction::class));
    }

    public function test_admin_manual_form_creates_paid_sale_and_commission(): void
    {
        $affiliateUser = User::factory()->create(['role' => UserRole::Affiliate]);
        $affiliate = Affiliate::query()->create([
            'user_id' => $affiliateUser->id,
            'status' => AffiliateStatus::Active,
        ]);
        $product = $this->product();
        $this->actingAsAdmin();

        Livewire::test(CreateTransaction::class)
            ->fillForm($this->formData($product, $affiliate->referral_code))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $transaction = Transaction::query()->sole();
        $this->assertSame('manual', $transaction->source->value);
        $this->assertSame($affiliate->id, $transaction->affiliate_id);
        $this->assertSame(500_000, (int) $transaction->commission->amount);
        $this->assertSame(1, Commission::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'subject_type' => $transaction->getMorphClass(),
            'subject_id' => $transaction->id,
            'action' => 'transaction.recorded',
        ]);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_manual_form_rejects_incorrect_total_without_writing_sale(): void
    {
        $product = $this->product();
        $this->actingAsAdmin();

        Livewire::test(CreateTransaction::class)
            ->fillForm($this->formData($product, null, ['total_paid' => 1]))
            ->call('create')
            ->assertHasFormErrors(['total_paid']);

        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, Commission::query()->count());
    }

    public function test_admin_can_search_transactions_and_filter_review_status(): void
    {
        $product = $this->product();
        $this->actingAsAdmin();

        $first = app(RecordTransaction::class)->handle($this->formData($product, null, [
            'source' => 'webhook',
            'external_ref' => 'INV-001',
        ]));
        $review = app(RecordTransaction::class)->handle($this->formData($product, 'UNKNOWN', [
            'source' => 'webhook',
            'external_ref' => 'INV-002',
        ]));

        Livewire::test(ListTransactions::class)
            ->searchTable('INV-001')
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$review])
            ->searchTable('INV-002')
            ->assertCanSeeTableRecords([$review])
            ->assertCanNotSeeTableRecords([$first])
            ->filterTable('needs_review', '1')
            ->assertCanSeeTableRecords([$review]);
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function product(): Product
    {
        return Product::query()->create([
            'code' => 'WEB-PRO',
            'name' => 'Website Pro',
            'type' => ProductType::Website,
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(Product $product, ?string $code, array $overrides = []): array
    {
        return array_replace([
            'external_ref' => 'INV-MANUAL-001',
            'product_id' => $product->id,
            'referral_code' => $code,
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
