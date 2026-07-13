<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use InvalidArgumentException;

class DueCampaignDispatcher
{
    public function __construct(private readonly CampaignStartService $startService) {}

    public function dispatchDue(): int
    {
        $campaigns = Campaign::query()
            ->where('status', Campaign::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get();

        $started = 0;

        foreach ($campaigns as $campaign) {
            try {
                $this->startService->startScheduledDue($campaign);
                $started++;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $started;
    }
}
