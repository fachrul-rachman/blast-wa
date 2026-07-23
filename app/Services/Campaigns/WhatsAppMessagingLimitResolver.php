<?php

namespace App\Services\Campaigns;

use App\Services\Meta\WhatsAppPhoneNumberClient;
use Illuminate\Support\Facades\Cache;
use Throwable;

class WhatsAppMessagingLimitResolver
{
    public function __construct(private readonly WhatsAppPhoneNumberClient $phoneNumberClient) {}

    public function resolve(): int
    {
        if ((string) config('services.whatsapp.daily_unique_recipient_limit_source', 'meta') !== 'meta') {
            return $this->fallbackLimit();
        }

        $cacheSeconds = max(60, (int) config('services.whatsapp.daily_unique_recipient_limit_cache_seconds', 3600));
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id', '');
        $cacheKey = 'whatsapp-messaging-limit:'.sha1($phoneNumberId);

        return (int) Cache::remember($cacheKey, $cacheSeconds, function (): int {
            try {
                $tier = $this->phoneNumberClient->messagingLimitTier();
            } catch (Throwable) {
                return $this->fallbackLimit();
            }

            return $this->limitFromTier($tier) ?? $this->fallbackLimit();
        });
    }

    private function fallbackLimit(): int
    {
        return max(1, (int) config('services.whatsapp.daily_unique_recipient_limit', 250));
    }

    private function limitFromTier(?string $tier): ?int
    {
        if ($tier === null) {
            return null;
        }

        $tier = strtoupper(trim($tier));

        if (is_numeric($tier)) {
            return max(1, (int) $tier);
        }

        return match ($tier) {
            'TIER_50' => 50,
            'TIER_250' => 250,
            'TIER_1K' => 1000,
            'TIER_2K' => 2000,
            'TIER_10K' => 10000,
            'TIER_100K' => 100000,
            'TIER_UNLIMITED' => 1000000000,
            default => null,
        };
    }
}
