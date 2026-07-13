<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignVariableMapping;

class CampaignPreviewService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function previews(Campaign $campaign, int $limit = 3): array
    {
        $bodyText = $campaign->template_snapshot['body_text'] ?? null;

        if (! is_string($bodyText) || $bodyText === '') {
            return [];
        }

        $mappings = $campaign->variableMappings()->get()->keyBy('variable');

        if ($mappings->isEmpty()) {
            return [];
        }

        return $campaign->recipients()
            ->where('validation_status', CampaignRecipient::VALIDATION_VALID)
            ->orderBy('source_row_number')
            ->limit($limit)
            ->get()
            ->map(function (CampaignRecipient $recipient) use ($bodyText, $mappings): array {
                $resolvedValues = [];

                foreach ($mappings as $variable => $mapping) {
                    $resolvedValues[$variable] = $this->resolveValue($recipient, $mapping);
                }

                return [
                    'recipient_id' => $recipient->id,
                    'name' => $recipient->name,
                    'phone_normalized' => $recipient->phone_normalized,
                    'resolved_values' => $resolvedValues,
                    'rendered_body' => $this->render($bodyText, $resolvedValues),
                ];
            })
            ->all();
    }

    private function resolveValue(CampaignRecipient $recipient, CampaignVariableMapping $mapping): string
    {
        if ($mapping->source_type === CampaignVariableMapping::SOURCE_FIXED) {
            return (string) $mapping->fixed_value;
        }

        return (string) ($recipient->row_data[$mapping->source_column_key] ?? '');
    }

    /**
     * @param  array<string, string>  $values
     */
    private function render(string $bodyText, array $values): string
    {
        foreach ($values as $variable => $value) {
            $bodyText = preg_replace('/{{\s*'.preg_quote($variable, '/').'\s*}}/', $value, $bodyText) ?? $bodyText;
        }

        return $bodyText;
    }
}
