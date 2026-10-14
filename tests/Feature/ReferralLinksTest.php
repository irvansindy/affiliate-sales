<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\Affiliates\Pages\ViewAffiliate;
use App\Filament\Affiliate\Resources\Products\Pages\ListProducts;
use App\Models\Affiliate;
use App\Models\Product;
use App\Models\User;
use App\Services\ReferralLinkBuilder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ReferralLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_returns_a_product_link_only_for_an_authorized_active_affiliate_and_ready_product(): void
    {
        $affiliate = $this->affiliate('AFF123');
        $ready = $this->product('WEB-PRO', 'https://shop.example.test/website');
        $missingDestination = $this->product('APP-PRO', null);
        $inactive = $this->product('WEB-OLD', 'https://shop.example.test/old', false);
        $builder = app(ReferralLinkBuilder::class);

        $this->assertSame(rtrim((string) config('app.url'), '/').'/r/AFF123?product=WEB-PRO', $builder->for($affiliate->user, $affiliate, $ready));
        $this->assertNull($builder->for($affiliate->user, $affiliate, $missingDestination));
        $this->assertNull($builder->for($affiliate->user, $affiliate, $inactive));
        $missingDestination->update(['landing_url' => 'javascript:alert(1)']);
        $this->assertNull($builder->for($affiliate->user, $affiliate, $missingDestination));

        $affiliate->update(['status' => AffiliateStatus::Suspended]);
        $this->assertNull($builder->for(User::factory()->create(['role' => UserRole::Admin]), $affiliate, $ready));
    }

    public function test_builder_rejects_another_affiliates_identity(): void
    {
        $mine = $this->affiliate('MINE01');
        $other = $this->affiliate('OTHER1');
        $product = $this->product('WEB-PRO', 'https://shop.example.test/website');

        $this->assertFalse(Gate::forUser($mine->user)->allows('view', $other));
        $this->assertThrows(fn () => app(ReferralLinkBuilder::class)->for($mine->user, $other, $product), AuthorizationException::class);
    }

    public function test_product_policy_grants_affiliates_read_only_access_to_active_products(): void
    {
        $affiliate = $this->affiliate('AFF123');
        $active = $this->product('WEB-PRO', 'https://shop.example.test/website');
        $inactive = $this->product('WEB-OLD', 'https://shop.example.test/old', false);

        $this->assertTrue(Gate::forUser($affiliate->user)->allows('viewAny', Product::class));
        $this->assertTrue(Gate::forUser($affiliate->user)->allows('view', $active));
        $this->assertFalse(Gate::forUser($affiliate->user)->allows('view', $inactive));
        $this->assertFalse(Gate::forUser($affiliate->user)->allows('create', Product::class));

        $affiliate->update(['status' => AffiliateStatus::Suspended]);
        $this->assertFalse(Gate::forUser($affiliate->user)->allows('viewAny', Product::class));
    }

    public function test_generated_link_opens_product_destination_and_records_a_click(): void
    {
        $affiliate = $this->affiliate('AFF123');
        $product = $this->product('WEB-PRO', 'https://shop.example.test/website');
        $link = app(ReferralLinkBuilder::class)->for($affiliate->user, $affiliate, $product);

        $this->get($link)->assertRedirect('https://shop.example.test/website?ref=AFF123');
        $this->assertDatabaseHas('referral_clicks', ['affiliate_id' => $affiliate->id, 'product_id' => $product->id]);
    }

    public function test_affiliate_sees_only_active_products_and_can_copy_own_ready_link(): void
    {
        $affiliate = $this->affiliate('AFF123');
        $this->affiliate('OTHER1');
        $ready = $this->product('WEB-PRO', 'https://shop.example.test/website');
        $notReady = $this->product('APP-PRO', null);
        $inactive = $this->product('WEB-OLD', 'https://shop.example.test/old', false);
        $this->actingAs($affiliate->user);
        Filament::setCurrentPanel(Filament::getPanel('affiliate'));

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$ready, $notReady])
            ->assertCanNotSeeTableRecords([$inactive])
            ->assertSee('/r/AFF123?product=WEB-PRO')
            ->assertSee(__('referrals.not_ready'))
            ->assertDontSee('/r/AFF123?product=APP-PRO')
            ->assertDontSee('/r/OTHER1?product=WEB-PRO');
        $this->get('/affiliate/products')->assertOk();
    }

    public function test_admin_detail_shows_links_for_selected_affiliate_only(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $selected = $this->affiliate('SELECT');
        $this->affiliate('OTHER1');
        $this->product('WEB-PRO', 'https://shop.example.test/website');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewAffiliate::class, ['record' => $selected->id])
            ->assertSee('/r/SELECT?product=WEB-PRO')
            ->assertDontSee('/r/OTHER1?product=WEB-PRO');
    }

    public function test_admin_detail_explains_why_a_suspended_affiliates_link_cannot_be_shared(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $affiliate = $this->affiliate('SUSPEN');
        $affiliate->update(['status' => AffiliateStatus::Suspended]);
        $this->product('WEB-PRO', 'https://shop.example.test/website');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewAffiliate::class, ['record' => $affiliate->id])
            ->assertSee(__('referrals.affiliate_inactive'))
            ->assertDontSee('/r/SUSPEN?product=WEB-PRO');
    }

    private function affiliate(string $code): Affiliate
    {
        return Affiliate::query()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Affiliate])->id,
            'referral_code' => $code,
            'status' => AffiliateStatus::Active,
        ]);
    }

    private function product(string $code, ?string $landingUrl, bool $isActive = true): Product
    {
        return Product::query()->create([
            'code' => $code,
            'name' => $code,
            'type' => ProductType::Website,
            'landing_url' => $landingUrl,
            'is_active' => $isActive,
        ]);
    }
}
