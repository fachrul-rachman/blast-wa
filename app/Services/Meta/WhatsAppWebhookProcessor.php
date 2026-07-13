<?php

namespace App\Services\Meta;

use App\Models\CampaignRecipient;
use App\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WhatsAppWebhookProcessor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void
    {
        foreach ($this->statuses($payload) as $statusPayload) {
            $this->processStatus($statusPayload);
        }
    }

    /**
     * @param  array<string, mixed>  $statusPayload
     */
    private function processStatus(array $statusPayload): void
    {
        $messageId = $statusPayload['id'] ?? null;
        $status = $statusPayload['status'] ?? null;
        $timestamp = $statusPayload['timestamp'] ?? null;

        if (! is_string($messageId) || blank($messageId) || ! is_string($status) || blank($status)) {
            return;
        }

        $eventTimestamp = is_numeric($timestamp)
            ? CarbonImmutable::createFromTimestamp((int) $timestamp)
            : null;
        $fingerprint = hash('sha256', $messageId.'|'.$status.'|'.($timestamp ?? ''));

        DB::transaction(function () use ($fingerprint, $messageId, $status, $statusPayload, $eventTimestamp): void {
            if (WebhookEvent::query()->where('fingerprint', $fingerprint)->exists()) {
                return;
            }

            WebhookEvent::query()->create([
                'fingerprint' => $fingerprint,
                'meta_message_id' => $messageId,
                'event_status' => $status,
                'event_timestamp' => $eventTimestamp,
                'payload' => $statusPayload,
                'processed_at' => now(),
            ]);

            /** @var CampaignRecipient|null $recipient */
            $recipient = CampaignRecipient::query()
                ->where('meta_message_id', $messageId)
                ->lockForUpdate()
                ->first();

            if (! $recipient instanceof CampaignRecipient) {
                return;
            }

            $this->applyStatus($recipient, $status, $statusPayload, $eventTimestamp);
        });
    }

    /**
     * @param  array<string, mixed>  $statusPayload
     */
    private function applyStatus(
        CampaignRecipient $recipient,
        string $status,
        array $statusPayload,
        ?CarbonImmutable $eventTimestamp,
    ): void {
        if ($status === CampaignRecipient::DELIVERY_FAILED) {
            $this->applyFailed($recipient, $statusPayload, $eventTimestamp);

            return;
        }

        if (! array_key_exists($status, $this->successfulRanks())) {
            return;
        }

        $currentRank = $this->successfulRanks()[$recipient->delivery_status] ?? 0;
        $nextRank = $this->successfulRanks()[$status];

        if ($nextRank < $currentRank) {
            return;
        }

        $timestampColumn = match ($status) {
            CampaignRecipient::DELIVERY_SENT => 'sent_at',
            CampaignRecipient::DELIVERY_DELIVERED => 'delivered_at',
            CampaignRecipient::DELIVERY_READ => 'read_at',
            default => null,
        };

        $updates = ['delivery_status' => $status];

        if ($timestampColumn !== null) {
            $updates[$timestampColumn] = $eventTimestamp ?? now();
        }

        $recipient->update($updates);
    }

    /**
     * @param  array<string, mixed>  $statusPayload
     */
    private function applyFailed(CampaignRecipient $recipient, array $statusPayload, ?CarbonImmutable $eventTimestamp): void
    {
        if (in_array($recipient->delivery_status, [
            CampaignRecipient::DELIVERY_DELIVERED,
            CampaignRecipient::DELIVERY_READ,
        ], true)) {
            return;
        }

        $error = data_get($statusPayload, 'errors.0');

        $recipient->update([
            'delivery_status' => CampaignRecipient::DELIVERY_FAILED,
            'failed_at' => $eventTimestamp ?? now(),
            'failure_code' => (string) (is_array($error) ? ($error['code'] ?? '') : ''),
            'failure_message' => (string) (is_array($error) ? ($error['title'] ?? $error['message'] ?? 'Webhook reported failure.') : 'Webhook reported failure.'),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function successfulRanks(): array
    {
        return [
            CampaignRecipient::DELIVERY_ACCEPTED => 1,
            CampaignRecipient::DELIVERY_SENT => 2,
            CampaignRecipient::DELIVERY_DELIVERED => 3,
            CampaignRecipient::DELIVERY_READ => 4,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function statuses(array $payload): array
    {
        $statuses = [];

        foreach (($payload['entry'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach (($entry['changes'] ?? []) as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $items = data_get($change, 'value.statuses', []);

                if (! is_array($items)) {
                    continue;
                }

                foreach ($items as $status) {
                    if (is_array($status)) {
                        $statuses[] = $status;
                    }
                }
            }
        }

        return $statuses;
    }
}
