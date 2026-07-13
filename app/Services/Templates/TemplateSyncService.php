<?php

namespace App\Services\Templates;

use App\Models\WhatsappTemplate;
use App\Services\Meta\WhatsAppTemplateClient;
use Illuminate\Support\Facades\DB;

class TemplateSyncService
{
    public function __construct(
        private readonly WhatsAppTemplateClient $client,
        private readonly TemplateSupportInspector $inspector,
    ) {}

    public function sync(): int
    {
        $templates = $this->client->fetchTemplates();
        $seenIds = [];
        $syncedAt = now();

        DB::transaction(function () use ($templates, &$seenIds, $syncedAt): void {
            foreach ($templates as $template) {
                $metaTemplateId = $template['id'] ?? null;

                if (! is_string($metaTemplateId) || blank($metaTemplateId)) {
                    continue;
                }

                $components = $this->components($template);
                $inspection = $this->inspector->inspect($components);
                $status = (string) ($template['status'] ?? '');
                $isApproved = $status === 'APPROVED';

                WhatsappTemplate::query()->updateOrCreate([
                    'meta_template_id' => $metaTemplateId,
                ], [
                    'name' => (string) ($template['name'] ?? $metaTemplateId),
                    'language_code' => (string) ($template['language'] ?? ''),
                    'category' => is_string($template['category'] ?? null) ? $template['category'] : null,
                    'status' => $status,
                    'body_text' => $inspection['body_text'],
                    'body_variables' => $inspection['body_variables'],
                    'components' => $components,
                    'is_supported' => $inspection['supported'],
                    'is_available' => $isApproved && $inspection['supported'],
                    'synced_at' => $syncedAt,
                ]);

                $seenIds[] = $metaTemplateId;
            }

            WhatsappTemplate::query()
                ->when($seenIds !== [], fn ($query) => $query->whereNotIn('meta_template_id', $seenIds))
                ->update(['is_available' => false]);
        });

        return count($seenIds);
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<int, array<string, mixed>>
     */
    private function components(array $template): array
    {
        $components = $template['components'] ?? [];

        if (! is_array($components)) {
            return [];
        }

        return array_values(array_filter($components, fn (mixed $component): bool => is_array($component)));
    }
}
