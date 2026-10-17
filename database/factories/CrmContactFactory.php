<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Affiliate;
use App\Models\CrmContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmContact>
 */
class CrmContactFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'affiliate_id' => Affiliate::query()->firstOrFail()->id,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'company' => fake()->company(),
        ];
    }

    public function forAffiliate(Affiliate $affiliate): static
    {
        return $this->state(['affiliate_id' => $affiliate->id]);
    }
}
