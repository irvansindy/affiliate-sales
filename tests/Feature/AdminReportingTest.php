<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\Commissions\Pages\ListCommissions;
use App\Filament\Admin\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Admin\Widgets\AdminSalesStats;
use App\Filament\Admin\Widgets\SalesTrendChart;
use App\Filament\Admin\Widgets\TopAffiliates;
use App\Filament\Exports\CommissionExporter;
use App\Filament\Exports\TransactionExporter;
use App\Models\Affiliate;
use App\Models\Commission;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AdminDashboardMetrics;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_excludes_refunds_and_top_affiliates_use_paid_total(): void
    {
        $first = $this->affiliate('Affiliator A');
        $second = $this->affiliate('Affiliator B');
        $this->record($first, 'INV-A', 200_000, CommissionStatus::Approved);
        $this->record($second, 'INV-B', 100_000, CommissionStatus::Paid);
        $this->record($first, 'INV-REFUND', 900_000, CommissionStatus::Cancelled, 'refunded');

        $metrics = app(AdminDashboardMetrics::class);

        $this->assertSame(['sales_count' => 2, 'sales_total' => 300_000, 'approved_commissions' => 20_000, 'paid_commissions' => 10_000], $metrics->summary());
        $this->assertSame(['Affiliator A', 'Affiliator B'], array_column($metrics->topAffiliates(), 'name'));
        $this->assertSame([200_000, 100_000], array_column($metrics->topAffiliates(), 'total_paid'));
    }

    public function test_six_month_trend_includes_zero_months_and_excludes_refunds(): void
    {
        $affiliate = $this->affiliate('Affiliator A');
        $this->record($affiliate, 'INV-OLD', 150_000, CommissionStatus::Paid, 'paid', now()->startOfMonth()->subMonths(2));
        $this->record($affiliate, 'INV-NOW', 250_000, CommissionStatus::Approved, 'paid', now());
        $this->record($affiliate, 'INV-REFUND', 800_000, CommissionStatus::Cancelled, 'refunded', now());

        $trend = app(AdminDashboardMetrics::class)->trend();

        $this->assertCount(6, $trend);
        $this->assertSame([0, 0, 0, 150_000, 0, 250_000], array_column($trend, 'total_paid'));
    }

    public function test_exported_text_is_safe_for_spreadsheets(): void
    {
        $affiliate = $this->affiliate('@berbahaya');
        [$transaction, $commission] = $this->record($affiliate, '=SUM(1,1)', 100_000, CommissionStatus::Approved);
        $transaction->update(['customer_name' => '+perintah']);

        $transactionCells = TransactionExporter::test()->export($transaction->load(['affiliate.user', 'product']));
        $commissionCells = CommissionExporter::test()->export($commission->load(['affiliate.user', 'transaction.product']));

        $this->assertContains("'=SUM(1,1)", $transactionCells);
        $this->assertContains("'+perintah", $transactionCells);
        $this->assertContains("'@berbahaya", $commissionCells);
    }

    public function test_admin_dashboard_widgets_show_paid_sales_and_rankings(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate('Affiliator A');
        $this->record($affiliate, 'INV-A', 200_000, CommissionStatus::Approved);
        $this->actingAs($admin);

        Livewire::test(AdminSalesStats::class)->assertSee('Rp 200.000');
        Livewire::test(SalesTrendChart::class)->assertSee('200000');
        Livewire::test(TopAffiliates::class)->assertSee('Affiliator A')->assertSee('Rp 200.000');
    }

    public function test_affiliate_cannot_render_admin_dashboard_widgets(): void
    {
        $affiliate = $this->affiliate('Affiliator A');
        $this->actingAs($affiliate->user);

        Livewire::test(AdminSalesStats::class)->assertForbidden();
        Livewire::test(SalesTrendChart::class)->assertForbidden();
        Livewire::test(TopAffiliates::class)->assertForbidden();
    }

    public function test_admin_can_queue_transaction_and_commission_exports_in_both_formats(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate('Affiliator A');
        $this->record($affiliate, 'INV-A', 200_000, CommissionStatus::Approved);
        $this->actingAs($admin);
        $exports = ExportAction::fake();

        Livewire::test(ListTransactions::class)->callAction('export')->assertHasNoActionErrors();
        Livewire::test(ListCommissions::class)->callAction('export')->assertHasNoActionErrors();

        $exports->assertDispatched(TransactionExporter::class, fn ($export, $query, $columns, $options, $formats): bool => $export->file_disk === 'local'
            && $query->count() === 1
            && in_array(ExportFormat::Csv, $formats, true)
            && in_array(ExportFormat::Xlsx, $formats, true));
        $exports->assertDispatched(CommissionExporter::class, fn ($export, $query, $columns, $options, $formats): bool => $export->file_disk === 'local'
            && $query->count() === 1
            && in_array(ExportFormat::Csv, $formats, true)
            && in_array(ExportFormat::Xlsx, $formats, true));
    }

    private function affiliate(string $name): Affiliate
    {
        $user = User::factory()->create(['name' => $name, 'role' => UserRole::Affiliate]);

        return Affiliate::query()->create(['user_id' => $user->id, 'status' => AffiliateStatus::Active]);
    }

    /** @return array{Transaction, Commission} */
    private function record(Affiliate $affiliate, string $reference, int $total, CommissionStatus $commissionStatus, string $transactionStatus = 'paid', ?\DateTimeInterface $paidAt = null): array
    {
        $product = Product::query()->create(['code' => 'WEB-'.uniqid(), 'name' => 'Website', 'type' => ProductType::Website]);
        $transaction = Transaction::query()->create([
            'external_ref' => $reference,
            'product_id' => $product->id,
            'affiliate_id' => $affiliate->id,
            'source' => 'manual',
            'purchase_type' => 'one_time',
            'sale_type' => 'new',
            'customer_name' => 'Pelanggan',
            'customer_email' => 'customer@example.test',
            'base_amount' => $total,
            'total_paid' => $total,
            'status' => $transactionStatus,
            'paid_at' => $paidAt ?? now(),
        ]);
        $commission = Commission::query()->create([
            'transaction_id' => $transaction->id,
            'affiliate_id' => $affiliate->id,
            'base_amount' => $total,
            'rate' => '10.00',
            'amount' => intdiv($total, 10),
        ]);
        $commission->forceFill(['status' => $commissionStatus])->save();

        return [$transaction, $commission];
    }
}
