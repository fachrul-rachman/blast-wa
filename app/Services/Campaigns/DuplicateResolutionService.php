<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DuplicateResolutionService
{
    public function __construct(private readonly CampaignSummaryService $summaryService) {}

    public function chooseWinner(Campaign $campaign, string $groupKey, int $winnerId): void
    {
        DB::transaction(function () use ($campaign, $groupKey, $winnerId): void {
            $group = $campaign->recipients()
                ->where('duplicate_group_key', $groupKey)
                ->get();

            if ($group->count() < 2 || ! $group->contains('id', $winnerId)) {
                throw new InvalidArgumentException('Invalid duplicate group selection.');
            }

            foreach ($group as $recipient) {
                $recipient->update([
                    'validation_status' => $recipient->id === $winnerId
                        ? CampaignRecipient::VALIDATION_VALID
                        : CampaignRecipient::VALIDATION_SKIPPED,
                    'is_duplicate_winner' => $recipient->id === $winnerId,
                    'validation_errors' => null,
                ]);
            }

            $campaign->refresh();
            $this->summaryService->refresh($campaign);
        });
    }
}
