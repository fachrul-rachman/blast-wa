<?php

namespace App\Services\Imports;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CampaignImportService
{
    public function __construct(
        private readonly RecipientFileParser $parser,
        private readonly ColumnDetector $detector,
        private readonly PhoneNumberValidator $phoneValidator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function import(Campaign $campaign, UploadedFile $file, ?string $phoneColumnKey, ?string $nameColumnKey): array
    {
        $path = $file->getRealPath();

        try {
            $parsed = $this->parser->parse($file);
            $phoneColumn = $phoneColumnKey ?: $this->detector->detectPhoneColumn($parsed->headers, $parsed->rows);
            $nameColumn = $nameColumnKey ?: $this->detector->detectNameColumn($parsed->headers, $parsed->rows, $phoneColumn);

            return $this->persist($campaign, $parsed, $phoneColumn, $nameColumn);
        } finally {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function remap(Campaign $campaign, string $phoneColumnKey, ?string $nameColumnKey): array
    {
        $summary = $campaign->import_summary;

        if (! is_array($summary) || ! is_array($summary['headers'] ?? null)) {
            throw new ImportException('Import data is not available for mapping correction.');
        }

        $rows = $campaign->recipients()
            ->orderBy('source_row_number')
            ->get(['source_row_number', 'row_data'])
            ->map(fn (CampaignRecipient $recipient): array => [
                'source_row_number' => $recipient->source_row_number,
                'data' => $recipient->row_data,
            ])
            ->all();

        return $this->persist($campaign, new ParsedImport($summary['headers'], $rows), $phoneColumnKey, $nameColumnKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function persist(Campaign $campaign, ParsedImport $parsed, ?string $phoneColumn, ?string $nameColumn): array
    {
        if ($phoneColumn === null || ! $this->hasHeader($parsed, $phoneColumn)) {
            throw new ImportException('Phone column could not be detected. Please choose it manually.');
        }

        if ($nameColumn !== null && ! $this->hasHeader($parsed, $nameColumn)) {
            throw new ImportException('Selected name column does not exist.');
        }

        $preparedRows = [];
        $phoneCounts = [];

        foreach ($parsed->rows as $row) {
            $phoneOriginal = $row['data'][$phoneColumn] ?? null;
            $validation = $this->phoneValidator->validate($phoneOriginal);
            $normalized = $validation['normalized'];

            if ($normalized !== null && $validation['errors'] === []) {
                $phoneCounts[$normalized] = ($phoneCounts[$normalized] ?? 0) + 1;
            }

            $preparedRows[] = [
                'source_row_number' => $row['source_row_number'],
                'row_data' => $row['data'],
                'name' => $nameColumn === null ? null : ($row['data'][$nameColumn] ?? null),
                'phone_original' => $phoneOriginal,
                'phone_normalized' => $normalized,
                'errors' => $validation['errors'],
            ];
        }

        $summary = $this->summary($preparedRows, $phoneCounts, $parsed->headers, $phoneColumn, $nameColumn);

        DB::transaction(function () use ($campaign, $preparedRows, $phoneCounts, $summary): void {
            $campaign->recipients()->delete();

            foreach ($preparedRows as $row) {
                $isDuplicate = $row['phone_normalized'] !== null
                    && ($phoneCounts[$row['phone_normalized']] ?? 0) > 1
                    && $row['errors'] === [];

                $status = match (true) {
                    $row['errors'] !== [] => CampaignRecipient::VALIDATION_INVALID,
                    $isDuplicate => CampaignRecipient::VALIDATION_DUPLICATE,
                    default => CampaignRecipient::VALIDATION_VALID,
                };

                CampaignRecipient::query()->create([
                    'campaign_id' => $campaign->id,
                    'source_row_number' => $row['source_row_number'],
                    'name' => $row['name'],
                    'phone_original' => $row['phone_original'],
                    'phone_normalized' => $row['phone_normalized'],
                    'row_data' => $row['row_data'],
                    'validation_status' => $status,
                    'validation_errors' => $row['errors'] === [] ? null : $row['errors'],
                    'duplicate_group_key' => $isDuplicate ? $row['phone_normalized'] : null,
                    'is_duplicate_winner' => false,
                    'delivery_status' => CampaignRecipient::DELIVERY_PENDING,
                ]);
            }

            $campaign->update(['import_summary' => $summary]);
        });

        return $summary;
    }

    private function hasHeader(ParsedImport $parsed, string $key): bool
    {
        foreach ($parsed->headers as $header) {
            if ($header['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, int>  $phoneCounts
     * @param  array<int, array{key: string, label: string}>  $headers
     * @return array<string, mixed>
     */
    private function summary(array $rows, array $phoneCounts, array $headers, string $phoneColumn, ?string $nameColumn): array
    {
        $invalid = 0;
        $duplicates = 0;
        $valid = 0;

        foreach ($rows as $row) {
            $isDuplicate = $row['phone_normalized'] !== null
                && ($phoneCounts[$row['phone_normalized']] ?? 0) > 1
                && $row['errors'] === [];

            if ($row['errors'] !== []) {
                $invalid++;
            } elseif ($isDuplicate) {
                $duplicates++;
            } else {
                $valid++;
            }
        }

        return [
            'headers' => $headers,
            'total_rows' => count($rows),
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'duplicate_rows' => $duplicates,
            'missing_data_rows' => 0,
            'skipped_rows' => 0,
            'send_eligible_rows' => $valid,
            'phone_column_key' => $phoneColumn,
            'name_column_key' => $nameColumn,
        ];
    }
}
