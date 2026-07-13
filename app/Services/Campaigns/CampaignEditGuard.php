<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use InvalidArgumentException;

class CampaignEditGuard
{
    public function ensureDraft(Campaign $campaign): void
    {
        if ($campaign->status !== Campaign::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft campaigns can be edited.');
        }
    }
}
