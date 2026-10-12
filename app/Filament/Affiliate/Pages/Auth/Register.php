<?php

declare(strict_types=1);

namespace App\Filament\Affiliate\Pages\Auth;

use App\Actions\RegisterAffiliate;
use App\Http\Responses\PendingRegistrationResponse;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class Register extends BaseRegister
{
    protected function handleRegistration(array $data): Model
    {
        return app(RegisterAffiliate::class)->handle($data);
    }

    public function register(): ?RegistrationResponse
    {
        $response = parent::register();

        if ($response === null) {
            return null;
        }

        Filament::auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        return new PendingRegistrationResponse;
    }
}
