<?php

namespace App\Services\Campaigns;

use App\Models\CampaignRecipient;
use App\Models\WhatsappDeliveryQuotaUsage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WhatsappDailyRecipientQuota
{
    public function assertAvailable(string $phoneNormalized): void
    {
        if (! (bool) config('services.whatsapp.daily_unique_recipient_limit_enabled', true)) {
            return;
        }

        DB::transaction(function () use ($phoneNormalized): void {
            $this->lockState();

            $limit = $this->limit();
            $cutoff = now()->subDay();

            $alreadyCounted = WhatsappDeliveryQuotaUsage::query()
                ->where('phone_normalized', $phoneNormalized)
                ->where('accepted_at', '>=', $cutoff)
                ->exists();

            if ($alreadyCounted) {
                return;
            }

            $used = WhatsappDeliveryQuotaUsage::query()
                ->where('accepted_at', '>=', $cutoff)
                ->distinct('phone_normalized')
                ->count('phone_normalized');

            if ($used < $limit) {
                return;
            }

            throw new DailyRecipientQuotaExceeded($this->delayUntilNextSlot($cutoff));
        });
    }

    public function recordAccepted(CampaignRecipient $recipient, CarbonInterface $acceptedAt): void
    {
        if (! (bool) config('services.whatsapp.daily_unique_recipient_limit_enabled', true)) {
            return;
        }

        if (! is_string($recipient->phone_normalized) || blank($recipient->phone_normalized)) {
            return;
        }

        DB::transaction(function () use ($recipient, $acceptedAt): void {
            $this->lockState();

            $cutoff = now()->subDay();
            $alreadyCounted = WhatsappDeliveryQuotaUsage::query()
                ->where('phone_normalized', $recipient->phone_normalized)
                ->where('accepted_at', '>=', $cutoff)
                ->exists();

            if ($alreadyCounted) {
                return;
            }

            WhatsappDeliveryQuotaUsage::query()->create([
                'phone_normalized' => $recipient->phone_normalized,
                'campaign_recipient_id' => $recipient->id,
                'accepted_at' => $acceptedAt,
            ]);
        });
    }

    /**
     * @return array{limit: int, used: int, remaining: int, resets_at: string|null}
     */
    public function snapshot(): array
    {
        $limit = $this->limit();
        $cutoff = now()->subDay();
        $used = WhatsappDeliveryQuotaUsage::query()
            ->where('accepted_at', '>=', $cutoff)
            ->distinct('phone_normalized')
            ->count('phone_normalized');

        $oldest = WhatsappDeliveryQuotaUsage::query()
            ->where('accepted_at', '>=', $cutoff)
            ->orderBy('accepted_at')
            ->value('accepted_at');

        $oldest = $oldest instanceof Carbon ? $oldest : ($oldest === null ? null : Carbon::parse($oldest));

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'resets_at' => $oldest?->copy()->addDay()->toISOString(),
        ];
    }

    private function lockState(): void
    {
        DB::table('whatsapp_delivery_quota_states')->insertOrIgnore([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('whatsapp_delivery_quota_states')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();
    }

    private function limit(): int
    {
        return max(1, (int) config('services.whatsapp.daily_unique_recipient_limit', 250));
    }

    private function delayUntilNextSlot(CarbonInterface $cutoff): int
    {
        $oldestAcceptedAt = WhatsappDeliveryQuotaUsage::query()
            ->where('accepted_at', '>=', $cutoff)
            ->orderBy('accepted_at')
            ->value('accepted_at');

        if ($oldestAcceptedAt === null) {
            return 60;
        }

        $availableAt = Carbon::parse($oldestAcceptedAt)->addDay()->addSeconds(5);

        $delaySeconds = (int) ceil(now()->diffInSeconds($availableAt, false));

        return max(60, $delaySeconds);
    }
}
