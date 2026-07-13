<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignVariableMapping;
use App\Models\MessageAttempt;
use App\Services\Meta\MetaApiException;
use App\Services\Meta\WhatsAppMediaClient;
use App\Services\Meta\WhatsAppMessageClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MessageDeliveryService
{
    public function __construct(
        private readonly WhatsAppMessageClient $client,
        private readonly WhatsAppMediaClient $mediaClient,
    ) {}

    public function deliver(int $recipientId): void
    {
        $shouldRetry = DB::transaction(function () use ($recipientId): bool {
            /** @var CampaignRecipient|null $recipient */
            $recipient = CampaignRecipient::query()
                ->with(['campaign', 'campaign.variableMappings'])
                ->whereKey($recipientId)
                ->lockForUpdate()
                ->first();

            if (! $recipient instanceof CampaignRecipient || ! $this->canAttempt($recipient)) {
                return false;
            }

            if ($recipient->validation_status !== CampaignRecipient::VALIDATION_VALID || blank($recipient->phone_normalized)) {
                $recipient->update(['delivery_status' => CampaignRecipient::DELIVERY_SKIPPED]);
                $this->completeCampaignIfDone($recipient->campaign);

                return false;
            }

            $attemptNumber = $recipient->attempt_count + 1;
            $attemptedAt = now();
            $parameters = $this->bodyParameters($recipient);
            $headerMedia = $this->headerMedia($recipient->campaign);

            $recipient->update([
                'attempt_count' => $attemptNumber,
                'last_attempt_at' => $attemptedAt,
            ]);

            $result = $this->client->sendTemplate(
                $recipient->phone_normalized,
                (string) $recipient->campaign->template_snapshot['name'],
                (string) $recipient->campaign->template_snapshot['language_code'],
                $parameters,
                $headerMedia,
            );

            if ($result['ok']) {
                $recipient->update([
                    'delivery_status' => CampaignRecipient::DELIVERY_ACCEPTED,
                    'meta_message_id' => $result['meta_message_id'],
                    'accepted_at' => $attemptedAt,
                    'failed_at' => null,
                    'failure_code' => null,
                    'failure_message' => null,
                ]);

                $this->recordAttempt($recipient, $attemptNumber, $attemptedAt, MessageAttempt::RESULT_ACCEPTED, $result);
                $this->completeCampaignIfDone($recipient->campaign);

                return false;
            }

            $recipient->update([
                'delivery_status' => CampaignRecipient::DELIVERY_FAILED,
                'failed_at' => $attemptedAt,
                'failure_code' => $result['error_code'],
                'failure_message' => $result['error_message'],
            ]);

            $this->recordAttempt($recipient, $attemptNumber, $attemptedAt, MessageAttempt::RESULT_FAILED, $result);
            $this->completeCampaignIfDone($recipient->campaign);

            return $this->isRetryable($result['status']);
        });

        if ($shouldRetry) {
            throw new MetaApiException('Meta message send failed with retryable response.');
        }
    }

    private function canAttempt(CampaignRecipient $recipient): bool
    {
        if ($recipient->meta_message_id !== null) {
            return false;
        }

        return in_array($recipient->delivery_status, [
            CampaignRecipient::DELIVERY_PENDING,
            CampaignRecipient::DELIVERY_QUEUED,
            CampaignRecipient::DELIVERY_FAILED,
        ], true);
    }

    /**
     * @return array<int, array{name: string|null, value: string}>
     */
    private function bodyParameters(CampaignRecipient $recipient): array
    {
        $variables = $recipient->campaign->template_snapshot['body_variables'] ?? [];
        $variables = is_array($variables) ? array_values(array_map('strval', $variables)) : [];
        $parameterNames = $this->bodyParameterNames($recipient->campaign);

        if ($variables === []) {
            return [];
        }

        $mappings = $recipient->campaign->variableMappings->keyBy('variable');
        $parameters = [];

        foreach ($variables as $index => $variable) {
            $mapping = $mappings->get($variable);
            $parameterName = $parameterNames[$index] ?? $variable;
            $parameterName = ctype_digit($parameterName) ? null : $parameterName;

            if (! $mapping instanceof CampaignVariableMapping) {
                $parameters[] = ['name' => $parameterName, 'value' => ''];

                continue;
            }

            $value = $mapping->source_type === CampaignVariableMapping::SOURCE_FIXED
                ? (string) $mapping->fixed_value
                : (string) ($recipient->row_data[$mapping->source_column_key] ?? '');

            $parameters[] = ['name' => $parameterName, 'value' => $value];
        }

        return $parameters;
    }

    /**
     * @return array{type: string, id: string}|null
     */
    private function headerMedia(Campaign $campaign): ?array
    {
        $components = $campaign->template_snapshot['components'] ?? [];

        if (! is_array($components)) {
            return null;
        }

        foreach ($components as $component) {
            if (! is_array($component) || strtoupper((string) ($component['type'] ?? '')) !== 'HEADER') {
                continue;
            }

            $format = strtolower((string) ($component['format'] ?? ''));

            if ($format !== 'image') {
                return null;
            }

            $link = data_get($component, 'example.header_handle.0');

            if (! is_string($link) || blank($link)) {
                return null;
            }

            $mediaId = Cache::remember(
                'whatsapp-header-media-id:'.sha1($link),
                now()->addDays(25),
                fn (): string => $this->mediaClient->uploadImageFromLink($link),
            );

            return ['type' => $format, 'id' => $mediaId];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function bodyParameterNames(Campaign $campaign): array
    {
        $components = $campaign->template_snapshot['components'] ?? [];

        if (! is_array($components)) {
            return [];
        }

        foreach ($components as $component) {
            if (! is_array($component) || strtoupper((string) ($component['type'] ?? '')) !== 'BODY') {
                continue;
            }

            $namedParameters = data_get($component, 'example.body_text_named_params');

            if (! is_array($namedParameters)) {
                return [];
            }

            return array_values(array_filter(array_map(
                fn (mixed $parameter): ?string => is_array($parameter) && is_string($parameter['param_name'] ?? null)
                    ? $parameter['param_name']
                    : null,
                $namedParameters,
            )));
        }

        return [];
    }

    /**
     * @param  array{ok: bool, status: int|null, meta_message_id: string|null, request: array<string, mixed>, response: array<string, mixed>|null, error_code: string|null, error_message: string|null}  $result
     */
    private function recordAttempt(
        CampaignRecipient $recipient,
        int $attemptNumber,
        CarbonInterface $attemptedAt,
        string $status,
        array $result,
    ): void {
        MessageAttempt::query()->create([
            'campaign_recipient_id' => $recipient->id,
            'attempt_number' => $attemptNumber,
            'request_payload_redacted' => $result['request'],
            'response_payload_redacted' => $result['response'],
            'meta_message_id' => $result['meta_message_id'],
            'result' => $status,
            'error_code' => $result['error_code'],
            'error_message' => $result['error_message'],
            'attempted_at' => $attemptedAt,
        ]);
    }

    private function isRetryable(?int $status): bool
    {
        return $status === 429 || ($status !== null && $status >= 500);
    }

    private function completeCampaignIfDone(Campaign $campaign): void
    {
        $hasActiveRecipients = $campaign->recipients()
            ->whereIn('delivery_status', [
                CampaignRecipient::DELIVERY_PENDING,
                CampaignRecipient::DELIVERY_QUEUED,
            ])
            ->exists();

        if (! $hasActiveRecipients && $campaign->status === Campaign::STATUS_PROCESSING) {
            $campaign->update([
                'status' => Campaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }
}
