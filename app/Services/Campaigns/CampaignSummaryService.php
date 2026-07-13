<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignRecipient;

class CampaignSummaryService
{
    public function refresh(Campaign $campaign): void
    {
        $summary = is_array($campaign->import_summary) ? $campaign->import_summary : [];
        $counts = $campaign->recipients()
            ->selectRaw('validation_status, count(*) as aggregate')
            ->groupBy('validation_status')
            ->pluck('aggregate', 'validation_status');

        $validRows = (int) ($counts[CampaignRecipient::VALIDATION_VALID] ?? 0);

        $summary['total_rows'] = (int) $campaign->recipients()->count();
        $summary['valid_rows'] = $validRows;
        $summary['invalid_rows'] = (int) ($counts[CampaignRecipient::VALIDATION_INVALID] ?? 0);
        $summary['duplicate_rows'] = (int) ($counts[CampaignRecipient::VALIDATION_DUPLICATE] ?? 0);
        $summary['missing_data_rows'] = (int) ($counts[CampaignRecipient::VALIDATION_MISSING_DATA] ?? 0);
        $summary['skipped_rows'] = (int) ($counts[CampaignRecipient::VALIDATION_SKIPPED] ?? 0);
        $summary['send_eligible_rows'] = $validRows;

        $campaign->update(['import_summary' => $summary]);
    }
}
