<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CrmLeadStage;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmLead>
 */
class CrmLeadFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $contact = CrmContact::query()->firstOrFail();

        return [
            'affiliate_id' => $contact->affiliate_id,
            'contact_id' => $contact->id,
            'product_id' => Product::query()->firstOrFail()->id,
            'stage' => CrmLeadStage::New,
            'notes' => null,
        ];
    }

    public function forContact(CrmContact $contact): static
    {
        return $this->state(['affiliate_id' => $contact->affiliate_id, 'contact_id' => $contact->id]);
    }
}
