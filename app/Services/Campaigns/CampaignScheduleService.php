<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CampaignScheduleService
{
    public function __construct(private readonly CampaignStartService $startService) {}

    public function schedule(Campaign $campaign, CarbonInterface $scheduledAt): void
    {
        if (! $scheduledAt->isFuture()) {
            throw new InvalidArgumentException('Scheduled time must be in the future.');
        }

        DB::transaction(function () use ($campaign, $scheduledAt): void {
            /** @var Campaign $lockedCampaign */
            $lockedCampaign = Campaign::query()
                ->whereKey($campaign->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedCampaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_SCHEDULED], true)) {
                throw new InvalidArgumentException('Campaign cannot be scheduled from its current status.');
            }

            $this->startService->ensureReady($lockedCampaign);

            $lockedCampaign->update([
                'status' => Campaign::STATUS_SCHEDULED,
                'scheduled_at' => $scheduledAt,
                'consent_confirmed_at' => $lockedCampaign->consent_confirmed_at ?? now(),
                'cancelled_at' => null,
                'last_error' => null,
            ]);
        });
    }

    public function cancel(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            /** @var Campaign $lockedCampaign */
            $lockedCampaign = Campaign::query()
                ->whereKey($campaign->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCampaign->status !== Campaign::STATUS_SCHEDULED) {
                throw new InvalidArgumentException('Only scheduled campaigns can be cancelled.');
            }

            $lockedCampaign->update([
                'status' => Campaign::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
        });
    }
}
