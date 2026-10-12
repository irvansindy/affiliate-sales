<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\Products\Pages\CreateProduct;
use App\Filament\Admin\Resources\Products\Pages\EditProduct;
use App\Filament\Admin\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_product_management_pages(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::query()->create([
            'code' => 'WEB-PRO',
            'name' => 'Website Profesional',
            'type' => ProductType::Website,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get('/admin/products')->assertOk();
        $this->get('/admin/products/create')->assertOk();
        $this->get('/admin/products/'.$product->getKey().'/edit')->assertOk();
    }

    public function test_affiliate_cannot_open_product_management_pages(): void
    {
        $affiliate = User::factory()->create(['role' => UserRole::Affiliate]);
        $product = Product::query()->create([
            'code' => 'APP-PRO',
            'name' => 'Mobile App Profesional',
            'type' => ProductType::MobileApp,
            'is_active' => true,
        ]);

        $this->actingAs($affiliate)->get('/admin/products')->assertForbidden();
        $this->get('/admin/products/create')->assertForbidden();
        $this->get('/admin/products/'.$product->getKey().'/edit')->assertForbidden();
    }

    public function test_product_policy_allows_admin_management_without_deletion(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::query()->create([
            'code' => 'WEB-BASIC',
            'name' => 'Website Basic',
            'type' => ProductType::Website,
            'is_active' => true,
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('create', Product::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $product));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $product));
        $this->assertFalse(Gate::forUser($admin)->allows('deleteAny', Product::class));
    }

    public function test_product_form_exposes_fields_needed_to_manage_catalog(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('code')
            ->assertFormFieldExists('name')
            ->assertFormFieldExists('type')
            ->assertFormFieldExists('is_active');
    }

    public function test_admin_can_create_product(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'code' => 'WEB-STARTER',
                'name' => 'Website Starter',
                'type' => ProductType::Website->value,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas('products', [
            'code' => 'WEB-STARTER',
            'name' => 'Website Starter',
            'type' => ProductType::Website->value,
            'is_active' => true,
        ]);
    }

    public function test_product_form_rejects_duplicate_code_and_invalid_required_values(): void
    {
        Product::query()->create([
            'code' => 'APP-BASIC',
            'name' => 'Mobile App Basic',
            'type' => ProductType::MobileApp,
            'is_active' => true,
        ]);
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'code' => 'APP-BASIC',
                'name' => null,
                'type' => 'desktop_app',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'code' => 'unique',
                'name' => 'required',
                'type',
            ])
            ->assertNotNotified();

        $this->assertSame(1, Product::query()->count());
    }

    public function test_product_form_rejects_values_longer_than_database_columns(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'code' => Str::repeat('A', 51),
                'name' => Str::repeat('B', 151),
                'type' => ProductType::Website->value,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'code' => 'max',
                'name' => 'max',
            ])
            ->assertNotNotified();

        $this->assertSame(0, Product::query()->count());
    }

    public function test_admin_can_edit_product_without_changing_its_unique_code(): void
    {
        $product = Product::query()->create([
            'code' => 'WEB-BUSINESS',
            'name' => 'Website Business',
            'type' => ProductType::Website,
            'is_active' => true,
        ]);
        $this->actingAsAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm([
                'code' => 'WEB-BUSINESS',
                'name' => 'Website Business Plus',
                'type' => ProductType::MobileApp->value,
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'code' => 'WEB-BUSINESS',
            'name' => 'Website Business Plus',
            'type' => ProductType::MobileApp->value,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_search_products_by_code_and_name(): void
    {
        $website = $this->product('WEB-STORE', 'Toko Online', ProductType::Website, true);
        $mobileApp = $this->product('APP-SALES', 'Aplikasi Sales', ProductType::MobileApp, true);
        $this->actingAsAdmin();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$website, $mobileApp])
            ->searchTable('WEB-STORE')
            ->assertCanSeeTableRecords([$website])
            ->assertCanNotSeeTableRecords([$mobileApp])
            ->searchTable('Aplikasi Sales')
            ->assertCanSeeTableRecords([$mobileApp])
            ->assertCanNotSeeTableRecords([$website]);
    }

    public function test_admin_can_filter_products_by_type_and_active_status(): void
    {
        $activeWebsite = $this->product('WEB-ACTIVE', 'Website Aktif', ProductType::Website, true);
        $inactiveWebsite = $this->product('WEB-INACTIVE', 'Website Nonaktif', ProductType::Website, false);
        $activeMobileApp = $this->product('APP-ACTIVE', 'Aplikasi Aktif', ProductType::MobileApp, true);
        $this->actingAsAdmin();

        Livewire::test(ListProducts::class)
            ->filterTable('type', ProductType::Website->value)
            ->assertCanSeeTableRecords([$activeWebsite, $inactiveWebsite])
            ->assertCanNotSeeTableRecords([$activeMobileApp])
            ->resetTableFilters()
            ->filterTable('is_active', '0')
            ->assertCanSeeTableRecords([$inactiveWebsite])
            ->assertCanNotSeeTableRecords([$activeWebsite, $activeMobileApp]);
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function product(string $code, string $name, ProductType $type, bool $isActive): Product
    {
        return Product::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_active' => $isActive,
        ]);
    }
}
