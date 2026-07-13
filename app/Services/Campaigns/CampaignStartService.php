<?php

namespace App\Services\Campaigns;

use App\Jobs\SendCampaignRecipientJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CampaignStartService
{
    /**
     * @return array<int, int>
     */
    public function startImmediate(Campaign $campaign): array
    {
        return $this->start($campaign, Campaign::STATUS_DRAFT, true);
    }

    /**
     * @return array<int, int>
     */
    public function startScheduledDue(Campaign $campaign): array
    {
        return $this->start($campaign, Campaign::STATUS_SCHEDULED, false);
    }

    public function ensureReady(Campaign $campaign): void
    {
        $summary = $campaign->import_summary;

        if (! is_array($summary)) {
            throw new InvalidArgumentException('Campaign recipients must be imported before sending.');
        }

        $variables = $campaign->template_snapshot['body_variables'] ?? [];
        $variables = is_array($variables) ? array_values(array_map('strval', $variables)) : [];

        if ($variables === []) {
            return;
        }

        $mappedVariables = $campaign->variableMappings()
            ->pluck('variable')
            ->map(fn (mixed $variable): string => (string) $variable)
            ->all();

        foreach ($variables as $variable) {
            if (! in_array($variable, $mappedVariables, true)) {
                throw new InvalidArgumentException('All template variables must be mapped before sending.');
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function start(Campaign $campaign, string $expectedStatus, bool $confirmConsent): array
    {
        $recipientIds = DB::transaction(function () use ($campaign, $expectedStatus, $confirmConsent): array {
            /** @var Campaign $lockedCampaign */
            $lockedCampaign = Campaign::query()
                ->whereKey($campaign->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCampaign->status !== $expectedStatus) {
                throw new InvalidArgumentException('Campaign cannot be started from its current status.');
            }

            $this->ensureReady($lockedCampaign);

            if ($expectedStatus === Campaign::STATUS_SCHEDULED && ($lockedCampaign->scheduled_at === null || $lockedCampaign->scheduled_at->isFuture())) {
                throw new InvalidArgumentException('Scheduled campaign is not due yet.');
            }

            $now = now();

            $updates = [
                'status' => Campaign::STATUS_PROCESSING,
                'started_at' => $now,
                'last_error' => null,
            ];

            if ($confirmConsent) {
                $updates['consent_confirmed_at'] = $now;
            }

            $lockedCampaign->update($updates);

            $lockedCampaign->recipients()
                ->where('delivery_status', CampaignRecipient::DELIVERY_PENDING)
                ->where('validation_status', '!=', CampaignRecipient::VALIDATION_VALID)
                ->update(['delivery_status' => CampaignRecipient::DELIVERY_SKIPPED]);

            $ids = $lockedCampaign->recipients()
                ->where('validation_status', CampaignRecipient::VALIDATION_VALID)
                ->where('delivery_status', CampaignRecipient::DELIVERY_PENDING)
                ->whereNull('meta_message_id')
                ->orderBy('source_row_number')
                ->pluck('id')
                ->all();

            $lockedCampaign->recipients()
                ->whereKey($ids)
                ->update(['delivery_status' => CampaignRecipient::DELIVERY_QUEUED]);

            if ($ids === []) {
                $lockedCampaign->update([
                    'status' => Campaign::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            }

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
