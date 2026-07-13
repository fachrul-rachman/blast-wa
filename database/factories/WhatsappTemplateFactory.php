<?php

namespace Database\Factories;

use App\Models\WhatsappTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappTemplate>
 */
class WhatsappTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meta_template_id' => fake()->unique()->uuid(),
            'name' => fake()->unique()->slug(2),
            'language_code' => 'id',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'body_text' => 'Halo {{1}}',
            'body_variables' => ['1'],
            'components' => [
                ['type' => 'BODY', 'text' => 'Halo {{1}}'],
            ],
            'is_supported' => true,
            'is_available' => true,
            'synced_at' => now(),
        ];
    }
}
