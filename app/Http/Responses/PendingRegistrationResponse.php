<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Illuminate\Http\RedirectResponse;

class PendingRegistrationResponse implements RegistrationResponse
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->route('filament.affiliate.auth.login')
            ->with('registration_pending', true);
    }
}
