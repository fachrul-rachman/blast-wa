<?php

namespace App\Services\Meta;

use Illuminate\Http\Client\Factory as HttpFactory;

class WhatsAppPhoneNumberClient
{
    public function __construct(private readonly HttpFactory $http) {}

    public function messagingLimitTier(): ?string
    {
        $baseUrl = rtrim((string) config('services.whatsapp.graph_api_base_url'), '/');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $token = config('services.whatsapp.access_token');

        if (! is_string($phoneNumberId) || blank($phoneNumberId) || ! is_string($token) || blank($token)) {
            throw new MetaApiException('Meta WhatsApp phone number credentials are not configured.');
        }

        $response = $this->http
            ->withToken($token)
            ->acceptJson()
            ->get("{$baseUrl}/{$phoneNumberId}", [
                'fields' => 'whatsapp_business_manager_messaging_limit',
            ]);

        if ($response->failed()) {
            throw new MetaApiException('Meta phone number limit request failed.');
        }

        $tier = data_get($response->json(), 'whatsapp_business_manager_messaging_limit');

        return is_string($tier) && filled($tier) ? $tier : null;
    }
}
