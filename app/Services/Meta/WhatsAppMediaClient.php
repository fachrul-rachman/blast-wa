<?php

namespace App\Services\Meta;

use Illuminate\Http\Client\Factory as HttpFactory;

class WhatsAppMediaClient
{
    public function __construct(private readonly HttpFactory $http) {}

    public function uploadImageFromLink(string $link): string
    {
        $baseUrl = rtrim((string) config('services.whatsapp.graph_api_base_url'), '/');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $token = config('services.whatsapp.access_token');

        if (! is_string($phoneNumberId) || blank($phoneNumberId) || ! is_string($token) || blank($token)) {
            throw new MetaApiException('Meta WhatsApp media credentials are not configured.');
        }

        $image = $this->http
            ->timeout(30)
            ->get($link);

        if ($image->failed() || blank($image->body())) {
            throw new MetaApiException('Unable to download template header image.');
        }

        $contentType = $image->header('Content-Type') ?: 'image/jpeg';
        $extension = str_contains($contentType, 'png') ? 'png' : 'jpg';
        $response = $this->http
            ->withToken($token)
            ->acceptJson()
            ->attach('file', $image->body(), "template-header.{$extension}", [
                'Content-Type' => $contentType,
            ])
            ->post("{$baseUrl}/{$phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $contentType,
            ]);

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];
        $mediaId = $payload['id'] ?? null;

        if ($response->failed() || ! is_string($mediaId) || blank($mediaId)) {
            throw new MetaApiException('Meta media upload failed.');
        }

        return $mediaId;
    }
}
