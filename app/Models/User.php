<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AffiliateStatus;
use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    // 'role' sengaja TIDAK mass-assignable: cegah privilege escalation lewat form registrasi.
    // Set eksplisit: $user->forceFill(['role' => UserRole::Admin])->save();
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function affiliate(): HasOne
    {
        return $this->hasOne(Affiliate::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isAffiliate(): bool
    {
        return $this->role === UserRole::Affiliate;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->isAdmin();
        }

        if ($panel->getId() === 'affiliate') {
            return $this->isAffiliate() && $this->affiliate()->where('status', AffiliateStatus::Active)->exists();
        }

        return false;
    }
}
