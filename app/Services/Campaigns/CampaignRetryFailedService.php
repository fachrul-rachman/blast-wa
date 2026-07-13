<?php

namespace App\Services\Campaigns;

use App\Jobs\SendCampaignRecipientJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CampaignRetryFailedService
{
    /**
     * @return array<int, int>
     */
    public function retry(Campaign $campaign): array
    {
        $recipientIds = DB::transaction(function () use ($campaign): array {
            /** @var Campaign $lockedCampaign */
            $lockedCampaign = Campaign::query()
                ->whereKey($campaign->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedCampaign->status, [Campaign::STATUS_COMPLETED, Campaign::STATUS_FAILED], true)) {
                throw new InvalidArgumentException('Only completed or failed campaigns can retry failed recipients.');
            }

            $ids = $lockedCampaign->recipients()
                ->where('delivery_status', CampaignRecipient::DELIVERY_FAILED)
                ->where('validation_status', CampaignRecipient::VALIDATION_VALID)
                ->whereNull('meta_message_id')
                ->orderBy('source_row_number')
                ->pluck('id')
                ->all();

            if ($ids === []) {
                throw new InvalidArgumentException('No failed recipients are available to retry.');
            }

            $lockedCampaign->recipients()
                ->whereKey($ids)
                ->update([
                    'delivery_status' => CampaignRecipient::DELIVERY_QUEUED,
                    'failed_at' => null,
                    'failure_code' => null,
                    'failure_message' => null,
                ]);

            $lockedCampaign->update([
                'status' => Campaign::STATUS_PROCESSING,
                'completed_at' => null,
                'last_error' => null,
            ]);

            return array_map('intval', $ids);
        });

        $delaySeconds = max(0, (int) config('services.whatsapp.send_delay_seconds', 1));

        foreach ($recipientIds as $index => $recipientId) {
            SendCampaignRecipientJob::dispatch($recipientId)
                ->delay(now()->addSeconds($delaySeconds * $index));
        }

        return $recipientIds;
    }
}
