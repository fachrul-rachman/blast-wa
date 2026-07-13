<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignVariableMapping;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VariableMappingService
{
    public function __construct(private readonly CampaignSummaryService $summaryService) {}

    /**
     * @param  array<int, array<string, mixed>>  $mappings
     */
    public function save(Campaign $campaign, array $mappings): void
    {
        $variables = $this->variables($campaign);
        $headers = $this->headers($campaign);
        $normalizedMappings = $this->normalizeMappings($variables, $headers, $mappings);

        DB::transaction(function () use ($campaign, $normalizedMappings): void {
            $campaign->variableMappings()->delete();

            foreach ($normalizedMappings as $mapping) {
                CampaignVariableMapping::query()->create([
                    'campaign_id' => $campaign->id,
                    'variable' => $mapping['variable'],
                    'source_type' => $mapping['source_type'],
                    'source_column_key' => $mapping['source_column_key'],
                    'fixed_value' => $mapping['fixed_value'],
                ]);
            }

            $this->applyMissingData($campaign);
            $campaign->refresh();
            $this->summaryService->refresh($campaign);
        });
    }

    /**
     * @return array<string, CampaignVariableMapping>
     */
    public function mappingsByVariable(Campaign $campaign): array
    {
        return $campaign->variableMappings()
            ->get()
            ->keyBy('variable')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function variables(Campaign $campaign): array
    {
        $variables = $campaign->template_snapshot['body_variables'] ?? [];

        return is_array($variables)
            ? array_values(array_map('strval', $variables))
            : [];
    }

    /**
     * @return array<int, string>
     */
    private function headers(Campaign $campaign): array
    {
        $summary = $campaign->import_summary;
        $headers = is_array($summary) ? ($summary['headers'] ?? []) : [];

        if (! is_array($headers)) {
            return [];
        }

        return array_values(array_map(
            fn (array $header): string => (string) $header['key'],
            $headers,
        ));
    }

    /**
     * @param  array<int, string>  $variables
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, mixed>>  $mappings
     * @return array<int, array{variable: string, source_type: string, source_column_key: string|null, fixed_value: string|null}>
     */
    private function normalizeMappings(array $variables, array $headers, array $mappings): array
    {
        $byVariable = [];

        foreach ($mappings as $mapping) {
            $variable = (string) ($mapping['variable'] ?? '');

            if ($variable !== '') {
                $byVariable[$variable] = $mapping;
            }
        }

        $normalized = [];

        foreach ($variables as $variable) {
            $mapping = $byVariable[$variable] ?? null;

            if (! is_array($mapping)) {
                throw new InvalidArgumentException('All required variables must be mapped.');
            }

            $sourceType = (string) ($mapping['source_type'] ?? '');
            $sourceColumnKey = $mapping['source_column_key'] ?? null;
            $fixedValue = $mapping['fixed_value'] ?? null;

            if ($sourceType === CampaignVariableMapping::SOURCE_COLUMN) {
                if (! is_string($sourceColumnKey) || ! in_array($sourceColumnKey, $headers, true)) {
                    throw new InvalidArgumentException('Selected column mapping is invalid.');
                }

                $fixedValue = null;
            } elseif ($sourceType === CampaignVariableMapping::SOURCE_FIXED) {
                if (! is_string($fixedValue) || trim($fixedValue) === '') {
                    throw new InvalidArgumentException('Fixed values cannot be empty.');
                }

                $fixedValue = trim($fixedValue);
                $sourceColumnKey = null;
            } else {
                throw new InvalidArgumentException('Mapping source type is invalid.');
            }

            $normalized[] = [
                'variable' => $variable,
                'source_type' => $sourceType,
                'source_column_key' => is_string($sourceColumnKey) ? $sourceColumnKey : null,
                'fixed_value' => $fixedValue,
            ];
        }

        return $normalized;
    }

    private function applyMissingData(Campaign $campaign): void
    {
        $mappings = $this->mappingsByVariable($campaign);

        $campaign->recipients()
            ->where(CampaignRecipient::query()->qualifyColumn('validation_status'), CampaignRecipient::VALIDATION_MISSING_DATA)
            ->update(['validation_status' => CampaignRecipient::VALIDATION_VALID, 'validation_errors' => null]);

        $recipients = $campaign->recipients()
            ->where('validation_status', CampaignRecipient::VALIDATION_VALID)
            ->get();

        foreach ($recipients as $recipient) {
            $missing = [];

            foreach ($mappings as $variable => $mapping) {
                if ($mapping->source_type !== CampaignVariableMapping::SOURCE_COLUMN) {
                    continue;
                }

                $value = $recipient->row_data[$mapping->source_column_key] ?? null;

                if ($value === null || trim((string) $value) === '') {
                    $missing[] = "Missing value for {$variable}.";
                }
            }

            if ($missing !== []) {
                $recipient->update([
                    'validation_status' => CampaignRecipient::VALIDATION_MISSING_DATA,
                    'validation_errors' => $missing,
                ]);
            }
        }
    }
}
