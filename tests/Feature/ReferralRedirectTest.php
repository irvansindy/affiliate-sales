<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\Products\Pages\CreateProduct;
use App\Models\Affiliate;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReferralRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_click_redirects_to_selected_product_and_records_hashed_ip(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product('WEB-PRO', 'https://shop.example.test/website?campaign=fall');

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->get('/r/'.$affiliate->referral_code.'?product='.$product->code);

        $response->assertRedirect('https://shop.example.test/website?campaign=fall&ref=AFF123');
        $response->assertCookie('ref', 'AFF123');
        $this->assertDatabaseHas('referral_clicks', [
            'affiliate_id' => $affiliate->id,
            'product_id' => $product->id,
            'ip_hash' => hash_hmac('sha256', '198.51.100.10', config('app.key')),
        ]);
        $this->assertDatabaseMissing('referral_clicks', ['ip_hash' => '198.51.100.10']);
        $this->assertEqualsWithDelta(30 * 24 * 60 * 60, $response->headers->getCookies()[0]->getExpiresTime() - time(), 60);
    }

    public function test_last_valid_click_replaces_previous_cookie(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product();

        $this->withCookie('ref', 'OLD-CODE')
            ->get('/r/'.$affiliate->referral_code.'?product='.$product->code)
            ->assertCookie('ref', 'AFF123');
    }

    public function test_existing_ref_parameter_on_product_url_is_replaced(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product('WEB-PRO', 'https://shop.example.test/website?ref=OLD&campaign=fall');

        $this->get('/r/'.$affiliate->referral_code.'?product='.$product->code)
            ->assertRedirect('https://shop.example.test/website?ref=AFF123&campaign=fall');
    }

    public function test_invalid_code_or_missing_product_does_not_record_click(): void
    {
        $affiliate = $this->affiliate();
        $this->product();

        $this->get('/r/UNKNOWN?product=WEB-PRO')->assertNotFound();
        $this->get('/r/'.$affiliate->referral_code.'?product=UNKNOWN')->assertNotFound();
        $this->get('/r/'.$affiliate->referral_code)->assertNotFound();

        $this->assertDatabaseCount('referral_clicks', 0);
    }

    public function test_inactive_affiliate_product_or_missing_destination_does_not_record_click(): void
    {
        $affiliate = $this->affiliate(AffiliateStatus::Suspended);
        $product = $this->product();
        $url = '/r/'.$affiliate->referral_code.'?product='.$product->code;

        $this->get($url)->assertNotFound();
        $affiliate->update(['status' => AffiliateStatus::Active]);
        $product->update(['is_active' => false]);
        $this->get($url)->assertNotFound();
        $product->update(['is_active' => true, 'landing_url' => null]);
        $this->get($url)->assertNotFound();

        $product->update(['landing_url' => 'javascript:alert(1)']);
        $this->get($url)->assertNotFound();

        $this->assertDatabaseCount('referral_clicks', 0);
    }

    public function test_referral_endpoint_is_rate_limited(): void
    {
        $affiliate = $this->affiliate();
        $product = $this->product();
        $url = '/r/'.$affiliate->referral_code.'?product='.$product->code;

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->get($url)->assertRedirect();
        }

        $this->get($url)->assertTooManyRequests();
        $this->assertDatabaseCount('referral_clicks', 60);
    }

    public function test_admin_can_set_product_destination_but_cannot_set_unsafe_url(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'code' => 'WEB-NEW', 'name' => 'Website Baru',
                'type' => ProductType::Website->value,
                'landing_url' => 'https://shop.example.test/product', 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', ['code' => 'WEB-NEW', 'landing_url' => 'https://shop.example.test/product']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'code' => 'WEB-BAD', 'name' => 'Website Buruk',
                'type' => ProductType::Website->value,
                'landing_url' => 'javascript:alert(1)', 'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['landing_url']);
    }

    private function affiliate(AffiliateStatus $status = AffiliateStatus::Active): Affiliate
    {
        return Affiliate::query()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Affiliate])->id,
            'referral_code' => 'AFF123',
            'status' => $status,
        ]);
    }

    private function product(string $code = 'WEB-PRO', ?string $landingUrl = 'https://shop.example.test/website'): Product
    {
        return Product::query()->create([
            'code' => $code, 'name' => 'Website Profesional',
            'type' => ProductType::Website, 'landing_url' => $landingUrl,
            'is_active' => true,
        ]);
    }
}
