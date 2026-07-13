<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignRecipient>
 */
class CampaignRecipientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'source_row_number' => fake()->numberBetween(2, 100),
            'name' => fake()->name(),
            'phone_original' => '6281234567890',
            'phone_normalized' => '6281234567890',
            'row_data' => [
                'nama' => fake()->name(),
                'nomor_wa' => '6281234567890',
            ],
            'validation_status' => CampaignRecipient::VALIDATION_VALID,
            'delivery_status' => CampaignRecipient::DELIVERY_PENDING,
        ];
    }
}
