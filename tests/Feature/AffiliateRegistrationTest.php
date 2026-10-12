<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\RegisterAffiliate;
use App\Enums\AffiliateStatus;
use App\Enums\UserRole;
use App\Filament\Affiliate\Pages\Auth\Register;
use App\Models\Affiliate;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AffiliateRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_is_public(): void
    {
        $this->get('/affiliate/register')->assertOk();
    }

    public function test_registration_creates_pending_affiliate_and_returns_to_login(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('affiliate'));

        Livewire::test(Register::class)->fillForm([
            'name' => 'Mitra Baru',
            'email' => 'mitra@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ])->set('data.role', 'admin')->set('data.status', 'active')
            ->call('register')->assertHasNoFormErrors()
            ->assertRedirect('/affiliate/login');

        $user = User::where('email', 'mitra@example.com')->sole();
        $this->assertSame(UserRole::Affiliate, $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertSame(AffiliateStatus::Pending, $user->affiliate->status);
        $this->assertNull($user->affiliate->approved_at);
        $this->assertNotEmpty($user->affiliate->referral_code);
        $this->assertGuest();
        $this->get('/affiliate/login')->assertSee('menunggu persetujuan admin');
        $this->get('/affiliate')->assertRedirect('/affiliate/login');
    }

    public function test_invalid_registration_does_not_create_records(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('affiliate'));

        Livewire::test(Register::class)->fillForm([
            'name' => '',
            'email' => 'invalid',
            'password' => 'short',
            'passwordConfirmation' => 'different',
        ])->call('register')->assertHasFormErrors(['name', 'email', 'password']);

        $this->assertSame(0, User::count());
        $this->assertSame(0, Affiliate::count());
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $user = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('affiliate'));

        Livewire::test(Register::class)->fillForm([
            'name' => 'Duplikat',
            'email' => $user->email,
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ])->call('register')->assertHasFormErrors(['email' => 'unique']);

        $this->assertSame(1, User::count());
        $this->assertSame(0, Affiliate::count());
    }

    public function test_registration_rolls_back_user_if_profile_cannot_be_saved(): void
    {
        DB::unprepared("CREATE TEMP TRIGGER fail_profile BEFORE INSERT ON affiliates BEGIN SELECT RAISE(ABORT, 'profile unavailable'); END");

        try {
            app(RegisterAffiliate::class)->handle([
                'name' => 'Mitra',
                'email' => 'rollback@example.com',
                'password' => 'password123',
            ]);
            $this->fail('Profile failure must abort registration.');
        } catch (QueryException) {
            $this->assertSame(0, User::count());
            $this->assertSame(0, Affiliate::count());
        } finally {
            DB::unprepared('DROP TRIGGER fail_profile');
        }
    }

    public function test_registration_is_rate_limited(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('affiliate'));
        $component = Livewire::test(Register::class);
        $component->call('register');
        $component->call('register');
        $component->fillForm([
            'name' => 'Mitra',
            'email' => 'limited@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ])->call('register');

        $this->assertSame(0, User::count());
        $this->assertSame(0, Affiliate::count());
    }
}
