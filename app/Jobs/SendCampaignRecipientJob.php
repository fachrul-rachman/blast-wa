<?php

namespace App\Jobs;

use App\Services\Campaigns\DailyRecipientQuotaExceeded;
use App\Services\Campaigns\MessageDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCampaignRecipientJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $recipientId) {}

    public function handle(MessageDeliveryService $deliveryService): void
    {
        try {
            $deliveryService->deliver($this->recipientId);
        } catch (DailyRecipientQuotaExceeded $exception) {
            self::dispatch($this->recipientId)->delay(now()->addSeconds($exception->delaySeconds));
        }
    }

    public function tries(): int
    {
        return max(1, (int) config('services.whatsapp.max_attempts', 3));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        $configured = (string) config('services.whatsapp.retry_backoff_seconds', '60,300,900');

        return collect(explode(',', $configured))
            ->map(fn (string $seconds): int => max(1, (int) trim($seconds)))
            ->filter()
            ->values()
            ->all();
    }
}
