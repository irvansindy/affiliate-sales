<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\SaveCrmLead;
use App\Enums\AffiliateStatus;
use App\Enums\CrmActivityType;
use App\Enums\CrmLeadStage;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\Affiliate;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CrmFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_must_use_contact_owned_by_the_same_affiliate(): void
    {
        $first = $this->affiliate('FIRST1');
        $second = $this->affiliate('SECOND');
        $product = $this->product();
        $foreignContact = CrmContact::query()->create(['affiliate_id' => $second->id, 'name' => 'Pelanggan B']);

        $this->assertThrows(fn () => app(SaveCrmLead::class)->handle($first->user, [
            'contact_id' => $foreignContact->id,
            'product_id' => $product->id,
            'stage' => CrmLeadStage::New->value,
        ]), ValidationException::class);
        $this->assertDatabaseCount('crm_leads', 0);

        $ownContact = CrmContact::query()->create(['affiliate_id' => $first->id, 'name' => 'Pelanggan A']);
        $lead = app(SaveCrmLead::class)->handle($first->user, [
            'affiliate_id' => $second->id,
            'contact_id' => $ownContact->id,
            'product_id' => $product->id,
            'stage' => CrmLeadStage::New->value,
        ]);

        $this->assertSame($first->id, $lead->affiliate_id);
        $this->assertSame($ownContact->id, $lead->contact_id);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('commissions', 0);
    }

    public function test_admin_cannot_reassign_a_lead_to_a_foreign_contact(): void
    {
        $first = $this->affiliate('FIRST1');
        $second = $this->affiliate('SECOND');
        $product = $this->product();
        $ownContact = CrmContact::query()->create(['affiliate_id' => $first->id, 'name' => 'Pelanggan A']);
        $foreignContact = CrmContact::query()->create(['affiliate_id' => $second->id, 'name' => 'Pelanggan B']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $lead = app(SaveCrmLead::class)->handle($admin, [
            'affiliate_id' => $first->id,
            'contact_id' => $ownContact->id,
            'product_id' => $product->id,
            'stage' => CrmLeadStage::New->value,
        ]);

        $this->assertThrows(fn () => app(SaveCrmLead::class)->handle($admin, [
            'affiliate_id' => $second->id,
            'contact_id' => $foreignContact->id,
            'product_id' => $product->id,
            'stage' => CrmLeadStage::Proposal->value,
        ], $lead), ValidationException::class);
        $this->assertSame($first->id, $lead->fresh()->affiliate_id);
        $this->assertSame($ownContact->id, $lead->fresh()->contact_id);
    }

    public function test_activity_policy_uses_its_leads_owner_and_active_status(): void
    {
        $first = $this->affiliate('FIRST1');
        $second = $this->affiliate('SECOND');
        $contact = CrmContact::query()->create(['affiliate_id' => $first->id, 'name' => 'Pelanggan A']);
        $lead = CrmLead::query()->create([
            'affiliate_id' => $first->id,
            'contact_id' => $contact->id,
            'product_id' => $this->product()->id,
            'stage' => CrmLeadStage::New,
        ]);
        $activity = CrmActivity::query()->create([
            'lead_id' => $lead->id,
            'author_user_id' => $first->user_id,
            'type' => CrmActivityType::Interaction,
            'summary' => 'Panggilan perkenalan',
        ]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue(Gate::forUser($first->user)->allows('view', $lead));
        $this->assertFalse(Gate::forUser($second->user)->allows('view', $lead));
        $this->assertFalse(Gate::forUser($second->user)->allows('update', $lead));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $lead));

        $this->assertTrue(Gate::forUser($first->user)->allows('view', $activity));
        $this->assertFalse(Gate::forUser($second->user)->allows('view', $activity));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $activity));

        $first->update(['status' => AffiliateStatus::Suspended]);
        $this->assertFalse(Gate::forUser($first->user)->allows('view', $activity));
    }

    public function test_inactive_affiliate_cannot_save_a_lead(): void
    {
        $affiliate = $this->affiliate('FIRST1', AffiliateStatus::Suspended);
        $contact = CrmContact::query()->create(['affiliate_id' => $affiliate->id, 'name' => 'Pelanggan A']);

        $this->assertThrows(fn () => app(SaveCrmLead::class)->handle($affiliate->user, [
            'contact_id' => $contact->id,
            'product_id' => $this->product()->id,
            'stage' => CrmLeadStage::New->value,
        ]), AuthorizationException::class);
    }

    private function affiliate(string $code, AffiliateStatus $status = AffiliateStatus::Active): Affiliate
    {
        return Affiliate::query()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Affiliate])->id,
            'referral_code' => $code,
            'status' => $status,
        ]);
    }

    private function product(): Product
    {
        return Product::query()->create(['code' => 'WEB-PRO', 'name' => 'Website Pro', 'type' => ProductType::Website, 'is_active' => true]);
    }
}
