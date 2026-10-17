<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\CompleteCrmFollowUp;
use App\Actions\SaveCrmActivity;
use App\Actions\SaveCrmLead;
use App\Enums\AffiliateStatus;
use App\Enums\CrmLeadStage;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Affiliate\Resources\CrmLeads\Pages\CreateCrmLead;
use App\Filament\Affiliate\Resources\CrmLeads\Pages\EditCrmLead;
use App\Filament\Affiliate\Resources\CrmLeads\Pages\ListCrmLeads;
use App\Filament\Affiliate\Resources\CrmLeads\RelationManagers\ActivitiesRelationManager;
use App\Models\Affiliate;
use App\Models\CrmContact;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CrmPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_won_stage_only_updates_crm_and_never_creates_money_records(): void
    {
        [$affiliate, $contact, $product] = $this->fixtures();
        $action = app(SaveCrmLead::class);
        $lead = $action->handle($affiliate->user, ['contact_id' => $contact->id, 'product_id' => $product->id, 'stage' => CrmLeadStage::New->value]);
        $action->handle($affiliate->user, ['contact_id' => $contact->id, 'product_id' => $product->id, 'stage' => CrmLeadStage::Won->value], $lead);

        $this->assertSame(CrmLeadStage::Won, $lead->fresh()->stage);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('commissions', 0);
    }

    public function test_follow_up_requires_due_date_and_can_be_completed_once(): void
    {
        [$affiliate, $contact, $product] = $this->fixtures();
        $lead = app(SaveCrmLead::class)->handle($affiliate->user, ['contact_id' => $contact->id, 'product_id' => $product->id, 'stage' => 'new']);

        $this->assertThrows(fn () => app(SaveCrmActivity::class)->handle($affiliate->user, $lead, ['type' => 'follow_up', 'summary' => 'Telepon']), ValidationException::class);
        $activity = app(SaveCrmActivity::class)->handle($affiliate->user, $lead, ['type' => 'follow_up', 'summary' => 'Telepon', 'due_at' => now()->subDay()->toDateTimeString()]);
        $this->assertNull($activity->completed_at);
        app(CompleteCrmFollowUp::class)->handle($affiliate->user, $activity);
        $this->assertNotNull($activity->fresh()->completed_at);
        $this->assertThrows(fn () => app(CompleteCrmFollowUp::class)->handle($affiliate->user, $activity), ValidationException::class);
    }

    public function test_other_affiliate_cannot_create_or_complete_activity(): void
    {
        [$affiliate, $contact, $product] = $this->fixtures();
        $other = $this->affiliate('OTHER1');
        $lead = app(SaveCrmLead::class)->handle($affiliate->user, ['contact_id' => $contact->id, 'product_id' => $product->id, 'stage' => 'new']);
        $activity = app(SaveCrmActivity::class)->handle($affiliate->user, $lead, ['type' => 'follow_up', 'summary' => 'Telepon', 'due_at' => now()->addDay()->toDateTimeString()]);

        $this->assertThrows(fn () => app(SaveCrmActivity::class)->handle($other->user, $lead, ['type' => 'interaction', 'summary' => 'Tidak boleh']), AuthorizationException::class);
        $this->assertThrows(fn () => app(CompleteCrmFollowUp::class)->handle($other->user, $activity), AuthorizationException::class);
    }

    public function test_affiliate_lead_routes_are_scoped_and_admin_can_open_all(): void
    {
        [$affiliate, $contact, $product] = $this->fixtures();
        $other = $this->affiliate('OTHER1');
        $lead = app(SaveCrmLead::class)->handle($affiliate->user, ['contact_id' => $contact->id, 'product_id' => $product->id, 'stage' => 'new']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($other->user)->get('/affiliate/crm-leads/'.$lead->id.'/edit')->assertNotFound();
        $this->actingAs($affiliate->user)->get('/affiliate/crm-leads/'.$lead->id.'/edit')->assertOk();
        $this->actingAs($admin)->get('/admin/crm-leads/'.$lead->id.'/edit')->assertOk();
    }

    public function test_both_panels_can_create_leads_with_validated_forms(): void
    {
        [$affiliate, $contact, $product] = $this->fixtures();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($affiliate->user);
        Filament::setCurrentPanel(Filament::getPanel('affiliate'));
        Livewire::test(CreateCrmLead::class)->fillForm(['contact_id' => $contact->id, 'product_id' => $product->id, 'stage' => 'new'])->call('create')->assertHasNoFormErrors();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(\App\Filament\Admin\Resources\CrmLeads\Pages\CreateCrmLead::class)->fillForm(['affiliate_id' => $affiliate->id, 'contact_id' => $contact->id, 'product_id' => $product->id, 'stage' => 'proposal'])->call('create')->assertHasNoFormErrors();

        $this->assertDatabaseCount('crm_leads', 2);
    }

    public function test_affiliate_lead_list_and_activity_panel_only_show_own_records(): void
    {
        [$affiliate, $contact, $product] = $this->fixtures();
        $other = $this->affiliate('OTHER1');
        $foreignContact = CrmContact::query()->create(['affiliate_id' => $other->id, 'name' => 'Rahasia']);
        $own = app(SaveCrmLead::class)->handle($affiliate->user, ['contact_id' => $contact->id, 'product_id' => $product->id, 'stage' => 'new']);
        $foreign = app(SaveCrmLead::class)->handle($other->user, ['contact_id' => $foreignContact->id, 'product_id' => $product->id, 'stage' => 'new']);

        $this->actingAs($affiliate->user);
        Filament::setCurrentPanel(Filament::getPanel('affiliate'));
        Livewire::test(ListCrmLeads::class)
            ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
        $this->assertFalse(ActivitiesRelationManager::canViewForRecord(
            $foreign,
            EditCrmLead::class
        ));
        Livewire::test(ActivitiesRelationManager::class, [
            'ownerRecord' => $own,
            'pageClass' => EditCrmLead::class,
        ])->assertOk()->callTableAction('addActivity', data: [
            'type' => 'follow_up',
            'summary' => 'Hubungi pelanggan',
            'due_at' => now()->subHour()->toDateTimeString(),
        ]);
        $activity = $own->activities()->firstOrFail();
        Livewire::test(ActivitiesRelationManager::class, [
            'ownerRecord' => $own,
            'pageClass' => EditCrmLead::class,
        ])->assertCanSeeTableRecords([$activity])->callTableAction('complete', $activity);
        $this->assertNotNull($activity->fresh()->completed_at);
    }

    public function test_stage_and_overdue_filters_show_only_matching_leads(): void
    {
        [$affiliate, $contact, $product] = $this->fixtures();
        $overdue = app(SaveCrmLead::class)->handle($affiliate->user, ['contact_id' => $contact->id, 'product_id' => $product->id, 'stage' => 'proposal']);
        $fresh = app(SaveCrmLead::class)->handle($affiliate->user, ['contact_id' => $contact->id, 'product_id' => $product->id, 'stage' => 'new']);
        app(SaveCrmActivity::class)->handle($affiliate->user, $overdue, ['type' => 'follow_up', 'summary' => 'Terlambat', 'due_at' => now()->subDay()->toDateTimeString()]);
        app(SaveCrmActivity::class)->handle($affiliate->user, $fresh, ['type' => 'follow_up', 'summary' => 'Besok', 'due_at' => now()->addDay()->toDateTimeString()]);

        $this->actingAs($affiliate->user);
        Filament::setCurrentPanel(Filament::getPanel('affiliate'));
        Livewire::test(ListCrmLeads::class)
            ->filterTable('stage', 'proposal')->assertCanSeeTableRecords([$overdue])->assertCanNotSeeTableRecords([$fresh]);
        Livewire::test(ListCrmLeads::class)
            ->filterTable('overdue', true)->assertCanSeeTableRecords([$overdue])->assertCanNotSeeTableRecords([$fresh]);
    }

    private function fixtures(): array
    {
        $affiliate = $this->affiliate('OWNER1');
        $contact = CrmContact::query()->create(['affiliate_id' => $affiliate->id, 'name' => 'Pelanggan']);
        $product = Product::query()->create(['code' => 'CRM-PROD', 'name' => 'Produk CRM', 'type' => ProductType::Website, 'is_active' => true]);

        return [$affiliate, $contact, $product];
    }

    private function affiliate(string $code): Affiliate
    {
        return Affiliate::query()->create(['user_id' => User::factory()->create(['role' => UserRole::Affiliate])->id, 'referral_code' => $code, 'status' => AffiliateStatus::Active]);
    }
}
