<?php

namespace App\Services\Meta;

use Illuminate\Http\Client\Factory as HttpFactory;

class WhatsAppTemplateClient
{
    public function __construct(private readonly HttpFactory $http) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchTemplates(): array
    {
        $baseUrl = rtrim((string) config('services.whatsapp.graph_api_base_url'), '/');
        $businessAccountId = config('services.whatsapp.business_account_id');
        $token = config('services.whatsapp.access_token');

        if (! is_string($businessAccountId) || blank($businessAccountId) || ! is_string($token) || blank($token)) {
            throw new MetaApiException('Meta WhatsApp credentials are not configured.');
        }

        $nextUrl = "{$baseUrl}/{$businessAccountId}/message_templates";
        $templates = [];

        while ($nextUrl !== null) {
            $response = $this->http
                ->withToken($token)
                ->acceptJson()
                ->get($nextUrl);

            if ($response->failed()) {
                throw new MetaApiException('Meta template sync failed.');
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new MetaApiException('Meta template sync returned an invalid response.');
            }

            foreach (($payload['data'] ?? []) as $template) {
                if (is_array($template)) {
                    $templates[] = $template;
                }
            }

            $nextUrl = data_get($payload, 'paging.next');
            $nextUrl = is_string($nextUrl) && filled($nextUrl) ? $nextUrl : null;
        }

        return $templates;
    }
}
