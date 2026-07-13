<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $template = WhatsappTemplate::factory()->create();

        return [
            'title' => fake()->sentence(3),
            'whatsapp_template_id' => $template->id,
            'template_snapshot' => [
                'id' => $template->id,
                'meta_template_id' => $template->meta_template_id,
                'name' => $template->name,
                'language_code' => $template->language_code,
                'category' => $template->category,
                'body_text' => $template->body_text,
                'body_variables' => $template->body_variables,
                'components' => $template->components,
            ],
            'status' => Campaign::STATUS_DRAFT,
        ];
    }
}
