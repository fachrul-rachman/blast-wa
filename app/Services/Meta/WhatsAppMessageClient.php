<?php

namespace App\Services\Meta;

use Illuminate\Http\Client\Factory as HttpFactory;

class WhatsAppMessageClient
{
    public function __construct(private readonly HttpFactory $http) {}

    /**
     * @param  array<int, array{name: string|null, value: string}>  $bodyParameters
     * @param  array{type: string, link: string}|null  $headerMedia
     * @return array{ok: bool, status: int|null, meta_message_id: string|null, request: array<string, mixed>, response: array<string, mixed>|null, error_code: string|null, error_message: string|null}
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        string $languageCode,
        array $bodyParameters,
        ?array $headerMedia = null,
    ): array {
        $baseUrl = rtrim((string) config('services.whatsapp.graph_api_base_url'), '/');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $token = config('services.whatsapp.access_token');

        if (! is_string($phoneNumberId) || blank($phoneNumberId) || ! is_string($token) || blank($token)) {
            throw new MetaApiException('Meta WhatsApp sending credentials are not configured.');
        }

        $payload = $this->payload($to, $templateName, $languageCode, $bodyParameters, $headerMedia);
        $response = $this->http
            ->withToken($token)
            ->acceptJson()
            ->post("{$baseUrl}/{$phoneNumberId}/messages", $payload);

        $responsePayload = $response->json();
        $responsePayload = is_array($responsePayload) ? $responsePayload : null;

        if ($response->failed()) {
            return [
                'ok' => false,
                'status' => $response->status(),
                'meta_message_id' => null,
                'request' => $payload,
                'response' => $responsePayload,
                'error_code' => (string) (data_get($responsePayload, 'error.code') ?? $response->status()),
                'error_message' => (string) (data_get($responsePayload, 'error.message') ?? 'Meta message send failed.'),
            ];
        }

        $metaMessageId = data_get($responsePayload, 'messages.0.id');

        if (! is_string($metaMessageId) || blank($metaMessageId)) {
            throw new MetaApiException('Meta message send returned an invalid response.');
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'meta_message_id' => $metaMessageId,
            'request' => $payload,
            'response' => $responsePayload,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    /**
     * @param  array<int, array{name: string|null, value: string}>  $bodyParameters
     * @param  array{type: string, link: string}|null  $headerMedia
     * @return array<string, mixed>
     */
    private function payload(
        string $to,
        string $templateName,
        string $languageCode,
        array $bodyParameters,
        ?array $headerMedia,
    ): array {
        $template = [
            'name' => $templateName,
            'language' => ['code' => $languageCode],
        ];

        $components = [];

        if ($headerMedia !== null) {
            $mediaType = strtolower($headerMedia['type']);

            $components[] = [
                'type' => 'header',
                'parameters' => [[
                    'type' => $mediaType,
                    $mediaType => [
                        'link' => $headerMedia['link'],
                    ],
                ]],
            ];
        }

        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    fn (array $parameter): array => array_filter([
                        'type' => 'text',
                        'parameter_name' => $parameter['name'],
                        'text' => $parameter['value'],
                    ], fn (mixed $value): bool => $value !== null && $value !== ''),
                    $bodyParameters,
                ),
            ];
        }

        if ($components !== []) {
            $template['components'] = $components;
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => $template,
        ];
    }
}
