<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Affiliate;

final class AttributionService
{
    /** @return array{affiliate_id: int|null, needs_review: bool, review_note: string|null} */
    public function resolve(?string $referralCode, string $customerEmail): array
    {
        $code = trim((string) $referralCode);

        if ($code === '') {
            return ['affiliate_id' => null, 'needs_review' => false, 'review_note' => null];
        }

        $affiliate = Affiliate::query()->with('user')
            ->where('referral_code', mb_strtoupper($code))
            ->first();

        if ($affiliate === null) {
            return $this->review('transactions.review.unknown_referral');
        }

        if (! $affiliate->isActive()) {
            return $this->review('transactions.review.inactive_affiliate');
        }

        if (strcasecmp(trim($customerEmail), trim($affiliate->user->email)) === 0) {
            return $this->review('transactions.review.self_referral');
        }

        return ['affiliate_id' => $affiliate->id, 'needs_review' => false, 'review_note' => null];
    }

    /** @return array{affiliate_id: null, needs_review: true, review_note: string} */
    private function review(string $translationKey): array
    {
        return ['affiliate_id' => null, 'needs_review' => true, 'review_note' => __($translationKey)];
    }
}
