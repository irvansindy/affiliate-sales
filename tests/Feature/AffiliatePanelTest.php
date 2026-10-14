<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\UpdateAffiliateProfile;
use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Affiliate\Pages\Profile;
use App\Filament\Affiliate\Resources\Commissions\Pages\ListCommissions;
use App\Filament\Affiliate\Resources\Payouts\Pages\ListPayouts;
use App\Filament\Affiliate\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Affiliate\Widgets\AffiliateStats;
use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\Payout;
use App\Models\Product;
use App\Models\ReferralClick;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AffiliateDashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AffiliatePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_only_current_affiliate_data(): void
    {
        $mine = $this->affiliate();
        $other = $this->affiliate();
        $this->record($mine, 'MINE', CommissionStatus::Approved, 100_000);
        $this->record($other, 'OTHER', CommissionStatus::Paid, 900_000);
        ReferralClick::query()->create(['affiliate_id' => $mine->id, 'ip_hash' => str_repeat('a', 64), 'clicked_at' => now()]);
        ReferralClick::query()->create(['affiliate_id' => $other->id, 'ip_hash' => str_repeat('b', 64), 'clicked_at' => now()]);

        $metrics = app(AffiliateDashboardMetrics::class)->for($mine);

        $this->assertSame(['clicks' => 1, 'sales' => 1, 'pending' => 0, 'approved' => 100_000, 'paid' => 0], $metrics);
        $this->actingAs($mine->user);
        Livewire::test(AffiliateStats::class)->assertSee('Rp 100.000')->assertDontSee('Rp 900.000');
        $this->get('/affiliate')->assertOk();
    }

    public function test_lists_and_direct_record_urls_hide_other_affiliates(): void
    {
        $mine = $this->affiliate();
        $other = $this->affiliate();
        [$ownTransaction, $ownCommission, $ownPayout] = $this->record($mine, 'MINE', CommissionStatus::Approved, 100_000);
        [$foreignTransaction, $foreignCommission, $foreignPayout] = $this->record($other, 'OTHER', CommissionStatus::Paid, 900_000);
        $this->actingAs($mine->user);

        Livewire::test(ListTransactions::class)->assertCanSeeTableRecords([$ownTransaction])->assertCanNotSeeTableRecords([$foreignTransaction]);
        Livewire::test(ListCommissions::class)->assertCanSeeTableRecords([$ownCommission])->assertCanNotSeeTableRecords([$foreignCommission]);
        Livewire::test(ListPayouts::class)->assertCanSeeTableRecords([$ownPayout])->assertCanNotSeeTableRecords([$foreignPayout]);
        $this->get('/affiliate/transactions/'.$foreignTransaction->id)->assertNotFound();
        $this->get('/affiliate/commissions/'.$foreignCommission->id)->assertNotFound();
        $this->get('/affiliate/payouts/'.$foreignPayout->id)->assertNotFound();
    }

    public function test_profile_update_preserves_bank_number_when_blank_and_audits_without_full_number(): void
    {
        $affiliate = $this->affiliate();
        $affiliate->update(['bank_account_number' => '1234567890']);

        app(UpdateAffiliateProfile::class)->handle($affiliate->user, [
            'name' => 'Nama Baru', 'phone' => '089876543210', 'bank_name' => 'Bank Baru',
            'bank_account_name' => 'Nama Rekening', 'bank_account_number' => '',
        ]);

        $this->assertSame('Nama Baru', $affiliate->user->fresh()->name);
        $this->assertSame('1234567890', $affiliate->fresh()->bank_account_number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate.profile_updated', 'subject_id' => $affiliate->id]);
        $audit = AuditLog::query()->where('action', 'affiliate.profile_updated')->firstOrFail();
        $this->assertStringNotContainsString('1234567890', json_encode([$audit->old, $audit->new], JSON_THROW_ON_ERROR));
    }

    public function test_profile_rejects_invalid_bank_number_and_cannot_change_ownership(): void
    {
        $affiliate = $this->affiliate();
        $other = $this->affiliate();

        $this->assertThrows(fn () => app(UpdateAffiliateProfile::class)->handle($affiliate->user, [
            'name' => 'Nama Baru', 'bank_account_number' => 'abc', 'user_id' => $other->user_id,
        ]), ValidationException::class);
        app(UpdateAffiliateProfile::class)->handle($affiliate->user, [
            'name' => 'Nama Baru', 'user_id' => $other->user_id, 'email' => 'other@example.test',
        ]);
        $this->assertSame($affiliate->user_id, $affiliate->fresh()->user_id);
        $this->assertNotSame('other@example.test', $affiliate->user->fresh()->email);
        $this->actingAs($affiliate->user)->get('/affiliate/profile')->assertOk();
        Livewire::test(Profile::class)->assertSee('Profil');
    }

    public function test_profile_form_updates_own_bank_without_exposing_full_number(): void
    {
        $affiliate = $this->affiliate();
        $affiliate->update(['bank_account_number' => '1234567890']);
        $this->actingAs($affiliate->user);

        $this->get('/affiliate/profile')->assertSee('****7890')->assertDontSee('1234567890');
        Livewire::test(Profile::class)->fillForm([
            'name' => 'Nama Affiliator', 'phone' => '081234567890',
            'bank_name' => 'Bank Contoh', 'bank_account_name' => 'Nama Affiliator',
            'bank_account_number' => '9876543210',
        ])->call('save')->assertHasNoFormErrors();

        $this->assertSame('9876543210', $affiliate->fresh()->bank_account_number);
        $this->assertSame('Nama Affiliator', $affiliate->user->fresh()->name);
        $this->get('/affiliate/profile')->assertSee('****3210')->assertDontSee('9876543210');
    }

    public function test_only_active_owner_can_update_affiliate_profile(): void
    {
        $affiliate = $this->affiliate();
        $other = $this->affiliate();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue(Gate::forUser($affiliate->user)->allows('updateProfile', $affiliate));
        $this->assertFalse(Gate::forUser($other->user)->allows('updateProfile', $affiliate));
        $this->assertFalse(Gate::forUser($admin)->allows('updateProfile', $affiliate));
        $affiliate->update(['status' => AffiliateStatus::Suspended]);
        $this->assertFalse(Gate::forUser($affiliate->user)->allows('updateProfile', $affiliate));
    }

    public function test_bank_number_change_is_audited_even_when_masked_suffix_is_unchanged(): void
    {
        $affiliate = $this->affiliate();
        $affiliate->update(['bank_account_number' => '1111117890']);

        app(UpdateAffiliateProfile::class)->handle($affiliate->user, [
            'name' => $affiliate->user->name,
            'bank_account_number' => '2222227890',
        ]);

        $audit = AuditLog::query()->where('action', 'affiliate.profile_updated')->firstOrFail();
        $this->assertTrue($audit->new['bank_account_number_changed']);
        $this->assertStringNotContainsString('2222227890', json_encode($audit->new, JSON_THROW_ON_ERROR));
    }

    public function test_transaction_product_filter_stays_scoped_to_current_affiliate(): void
    {
        $mine = $this->affiliate();
        $other = $this->affiliate();
        [$first] = $this->record($mine, 'FIRST', CommissionStatus::Pending, 100_000);
        [$second] = $this->record($mine, 'SECOND', CommissionStatus::Pending, 100_000);
        [$foreign] = $this->record($other, 'FOREIGN', CommissionStatus::Pending, 100_000);
        $this->actingAs($mine->user);

        Livewire::test(ListTransactions::class)->filterTable('product_id', $first->product_id)
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$second, $foreign]);
    }

    public function test_suspended_affiliate_cannot_open_new_panel_pages(): void
    {
        $affiliate = $this->affiliate();
        $affiliate->update(['status' => AffiliateStatus::Suspended]);
        $this->actingAs($affiliate->user);

        $this->get('/affiliate/profile')->assertForbidden();
        $this->get('/affiliate/transactions')->assertForbidden();
        $this->get('/affiliate/commissions')->assertForbidden();
        $this->get('/affiliate/payouts')->assertForbidden();
    }

    private function affiliate(): Affiliate
    {
        return Affiliate::query()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Affiliate])->id,
            'status' => AffiliateStatus::Active,
        ]);
    }

    /** @return array{Transaction, Commission, Payout} */
    private function record(Affiliate $affiliate, string $reference, CommissionStatus $status, int $amount): array
    {
        $product = Product::query()->create(['code' => 'WEB-'.$reference, 'name' => 'Website '.$reference, 'type' => ProductType::Website]);
        $transaction = Transaction::query()->create([
            'external_ref' => $reference, 'product_id' => $product->id, 'affiliate_id' => $affiliate->id,
            'source' => 'manual', 'purchase_type' => 'one_time', 'sale_type' => 'new',
            'customer_name' => 'Pelanggan '.$reference, 'customer_email' => strtolower($reference).'@example.test',
            'base_amount' => $amount * 10, 'total_paid' => $amount * 10, 'paid_at' => now(),
        ]);
        $payout = Payout::query()->create(['affiliate_id' => $affiliate->id, 'total_amount' => $amount]);
        $commission = Commission::query()->create([
            'transaction_id' => $transaction->id, 'affiliate_id' => $affiliate->id,
            'base_amount' => $amount * 10, 'rate' => '10.00', 'amount' => $amount, 'payout_id' => $payout->id,
        ]);
        $commission->forceFill(['status' => $status])->save();

        return [$transaction, $commission, $payout];
    }
}
