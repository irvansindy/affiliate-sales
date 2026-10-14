<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Affiliate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ReferralLinkBuilder
{
    public function for(User $actor, Affiliate $affiliate, Product $product): ?string
    {
        Gate::forUser($actor)->authorize('view', $affiliate);

        if (! $affiliate->isActive() || ! $product->is_active || ! $this->hasValidDestination($product)) {
            return null;
        }

        return route('referral.redirect', [
            'referral_code' => $affiliate->referral_code,
            'product' => $product->code,
        ]);
    }

    private function hasValidDestination(Product $product): bool
    {
        return is_string($product->landing_url)
            && filter_var($product->landing_url, FILTER_VALIDATE_URL)
            && in_array(parse_url($product->landing_url, PHP_URL_SCHEME), ['http', 'https'], true);
    }
}
