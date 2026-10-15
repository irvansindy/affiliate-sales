<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PurchaseType;
use App\Models\Affiliate;
use App\Models\Product;
use App\Models\SalesSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesSubmission>
 */
class SalesSubmissionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'affiliate_id' => Affiliate::query()->firstOrFail()->id,
            'product_id' => Product::query()->firstOrFail()->id,
            'purchase_type' => PurchaseType::OneTime,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->unique()->safeEmail(),
            'claimed_ref' => null,
            'proof_path' => 'sales-proofs/'.fake()->uuid().'.pdf',
        ];
    }
}
