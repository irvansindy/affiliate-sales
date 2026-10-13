<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\Product;
use App\Models\ReferralClick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Uri;

class ReferralRedirectController extends Controller
{
    public function __invoke(Request $request, string $referral_code): RedirectResponse
    {
        $productCode = $request->query('product');
        abort_unless(is_string($productCode) && $productCode !== '' && strlen($productCode) <= 50, 404);

        $affiliate = Affiliate::query()
            ->where('referral_code', $referral_code)
            ->where('status', AffiliateStatus::Active->value)
            ->firstOrFail();

        $product = Product::query()
            ->where('code', $productCode)
            ->where('is_active', true)
            ->whereNotNull('landing_url')
            ->firstOrFail();

        abort_unless(
            filter_var($product->landing_url, FILTER_VALIDATE_URL)
                && in_array(parse_url($product->landing_url, PHP_URL_SCHEME), ['http', 'https'], true),
            404,
        );

        $ip = $request->ip();
        ReferralClick::query()->create([
            'affiliate_id' => $affiliate->id,
            'product_id' => $product->id,
            'ip_hash' => $ip === null ? null : hash_hmac('sha256', $ip, (string) config('app.key')),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'clicked_at' => now(),
        ]);

        return Uri::of($product->landing_url)
            ->withQuery(['ref' => $affiliate->referral_code])
            ->redirect()
            ->withCookie(cookie('ref', $affiliate->referral_code, 30 * 24 * 60, secure: $request->isSecure(), sameSite: 'lax'));
    }
}
