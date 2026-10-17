<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\SaveCrmContact;
use App\Enums\AffiliateStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\CrmContacts\Pages\CreateCrmContact as AdminCreateContact;
use App\Filament\Affiliate\Resources\CrmContacts\Pages\CreateCrmContact as AffiliateCreateContact;
use App\Filament\Affiliate\Resources\CrmContacts\Pages\ListCrmContacts as AffiliateListContacts;
use App\Models\Affiliate;
use App\Models\CrmContact;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CrmContactManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_contact_owner_is_server_derived_and_cannot_be_changed(): void
    {
        $first = $this->affiliate('FIRST1');
        $second = $this->affiliate('SECOND');
        $contact = app(SaveCrmContact::class)->handle($first->user, [
            'affiliate_id' => $second->id,
            'name' => 'Pelanggan A',
            'email' => 'buyer@example.test',
        ]);

        $this->assertSame($first->id, $contact->affiliate_id);
        $this->assertThrows(fn () => app(SaveCrmContact::class)->handle($second->user, [
            'name' => 'Diambil alih',
        ], $contact), AuthorizationException::class);
        $this->assertSame('Pelanggan A', $contact->fresh()->name);

        app(SaveCrmContact::class)->handle($first->user, [
            'affiliate_id' => $second->id,
            'name' => 'Pelanggan A Diperbarui',
            'email' => 'buyer@example.test',
        ], $contact);
        $this->assertSame($first->id, $contact->fresh()->affiliate_id);
        $this->assertSame('Pelanggan A Diperbarui', $contact->fresh()->name);
    }

    public function test_admin_must_choose_an_affiliate_and_cannot_transfer_existing_contact(): void
    {
        $first = $this->affiliate('FIRST1');
        $second = $this->affiliate('SECOND');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertThrows(fn () => app(SaveCrmContact::class)->handle($admin, ['name' => 'Tanpa pemilik']), ValidationException::class);
        $contact = app(SaveCrmContact::class)->handle($admin, ['affiliate_id' => $first->id, 'name' => 'Pelanggan A']);
        $this->assertSame($first->id, $contact->affiliate_id);

        $this->assertThrows(fn () => app(SaveCrmContact::class)->handle($admin, [
            'affiliate_id' => $second->id,
            'name' => 'Pelanggan A',
        ], $contact), ValidationException::class);
        $this->assertSame($first->id, $contact->fresh()->affiliate_id);
    }

    public function test_invalid_contact_fields_are_rejected_on_server(): void
    {
        $affiliate = $this->affiliate('FIRST1');

        $this->assertThrows(fn () => app(SaveCrmContact::class)->handle($affiliate->user, [
            'name' => '',
            'email' => 'invalid',
            'phone' => str_repeat('1', 31),
        ]), ValidationException::class);
        $this->assertDatabaseCount('crm_contacts', 0);
    }

    public function test_policy_and_direct_urls_block_other_affiliates_contacts(): void
    {
        $first = $this->affiliate('FIRST1');
        $second = $this->affiliate('SECOND');
        $contact = CrmContact::query()->create(['affiliate_id' => $first->id, 'name' => 'Rahasia']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue(Gate::forUser($first->user)->allows('update', $contact));
        $this->assertFalse(Gate::forUser($second->user)->allows('update', $contact));
        $this->actingAs($second->user)->get('/affiliate/crm-contacts/'.$contact->id.'/edit')->assertNotFound();
        $this->actingAs($first->user)->get('/affiliate/crm-contacts/'.$contact->id.'/edit')->assertOk();
        $this->actingAs($admin)->get('/admin/crm-contacts/'.$contact->id.'/edit')->assertOk();
        $this->actingAs($first->user)->get('/admin/crm-contacts')->assertForbidden();
    }

    public function test_suspended_affiliate_cannot_open_or_save_contacts(): void
    {
        $affiliate = $this->affiliate('FIRST1', AffiliateStatus::Suspended);

        $this->actingAs($affiliate->user)->get('/affiliate/crm-contacts')->assertForbidden();
        $this->assertThrows(fn () => app(SaveCrmContact::class)->handle($affiliate->user, ['name' => 'Pelanggan']), AuthorizationException::class);
    }

    public function test_affiliate_contact_list_is_scoped_to_its_owner(): void
    {
        $first = $this->affiliate('FIRST1');
        $second = $this->affiliate('SECOND');
        $own = CrmContact::query()->create(['affiliate_id' => $first->id, 'name' => 'Pelanggan Sendiri']);
        $foreign = CrmContact::query()->create(['affiliate_id' => $second->id, 'name' => 'Pelanggan Rahasia']);

        $this->actingAs($first->user);
        Filament::setCurrentPanel(Filament::getPanel('affiliate'));
        Livewire::test(AffiliateListContacts::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_both_panels_create_contacts_through_validated_forms(): void
    {
        $affiliate = $this->affiliate('FIRST1');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($affiliate->user);
        Filament::setCurrentPanel(Filament::getPanel('affiliate'));
        Livewire::test(AffiliateCreateContact::class)->fillForm([
            'name' => 'Pelanggan Affiliator',
            'email' => 'affiliate-buyer@example.test',
        ])->call('create')->assertHasNoFormErrors();

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(AdminCreateContact::class)->fillForm([
            'affiliate_id' => $affiliate->id,
            'name' => 'Pelanggan Admin',
        ])->call('create')->assertHasNoFormErrors();

        $this->assertDatabaseHas('crm_contacts', ['affiliate_id' => $affiliate->id, 'name' => 'Pelanggan Affiliator']);
        $this->assertDatabaseHas('crm_contacts', ['affiliate_id' => $affiliate->id, 'name' => 'Pelanggan Admin']);
    }

    private function affiliate(string $code, AffiliateStatus $status = AffiliateStatus::Active): Affiliate
    {
        return Affiliate::query()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Affiliate])->id,
            'referral_code' => $code,
            'status' => $status,
        ]);
    }
}
