<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CrmActivityType;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmActivity>
 */
class CrmActivityFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $lead = CrmLead::query()->with('affiliate')->firstOrFail();

        return [
            'lead_id' => $lead->id,
            'author_user_id' => $lead->affiliate->user_id,
            'type' => CrmActivityType::Interaction,
            'summary' => fake()->sentence(),
            'occurred_at' => now(),
            'due_at' => null,
            'completed_at' => null,
        ];
    }

    public function forLead(CrmLead $lead): static
    {
        return $this->state(['lead_id' => $lead->id, 'author_user_id' => $lead->affiliate->user_id]);
    }
}
