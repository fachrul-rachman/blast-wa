<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignRecipient;

class CampaignDeliverySummaryService
{
    /**
     * @return array<string, int>
     */
    public function summary(Campaign $campaign): array
    {
        $counts = $campaign->recipients()
            ->selectRaw('delivery_status, count(*) as aggregate')
            ->groupBy('delivery_status')
            ->pluck('aggregate', 'delivery_status');

        return [
            'pending' => (int) ($counts[CampaignRecipient::DELIVERY_PENDING] ?? 0),
            'queued' => (int) ($counts[CampaignRecipient::DELIVERY_QUEUED] ?? 0),
            'accepted' => (int) ($counts[CampaignRecipient::DELIVERY_ACCEPTED] ?? 0),
            'sent' => (int) ($counts[CampaignRecipient::DELIVERY_SENT] ?? 0),
            'delivered' => (int) ($counts[CampaignRecipient::DELIVERY_DELIVERED] ?? 0),
            'read' => (int) ($counts[CampaignRecipient::DELIVERY_READ] ?? 0),
            'failed' => (int) ($counts[CampaignRecipient::DELIVERY_FAILED] ?? 0),
            'skipped' => (int) ($counts[CampaignRecipient::DELIVERY_SKIPPED] ?? 0),
        ];
    }
}
