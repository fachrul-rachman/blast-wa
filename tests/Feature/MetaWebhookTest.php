<?php

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

beforeEach(function () {
    WebhookEvent::query()->delete();
    CampaignRecipient::query()->delete();
    Campaign::query()->delete();
});

test('meta webhook verification returns challenge for valid verify token', function () {
    config(['services.whatsapp.webhook_verify_token' => 'verify-token']);

    $this->get('/webhooks/meta/whatsapp?hub.mode=subscribe&hub.verify_token=verify-token&hub.challenge=abc123')
        ->assertOk()
        ->assertSee('abc123', false);
});

test('meta webhook verification rejects invalid verify token', function () {
    config(['services.whatsapp.webhook_verify_token' => 'verify-token']);

    $this->get('/webhooks/meta/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc123')
        ->assertForbidden();
});

test('webhook updates recipient status and stores event idempotently', function () {
    config(['services.whatsapp.app_secret' => null]);
    $recipient = webhookRecipient(['delivery_status' => 'accepted']);

    $payload = webhookPayload('wamid.123', 'delivered', '1710000000');

    postWebhook($payload)->assertOk();
    postWebhook($payload)->assertOk();

    $recipient->refresh();

    expect($recipient->delivery_status)->toBe('delivered')
        ->and($recipient->delivered_at)->not->toBeNull()
        ->and(WebhookEvent::query()->count())->toBe(1);
});

test('webhook does not downgrade successful delivery status', function () {
    config(['services.whatsapp.app_secret' => null]);
    $recipient = webhookRecipient([
        'delivery_status' => 'read',
        'read_at' => now(),
    ]);

    postWebhook(webhookPayload('wamid.123', 'delivered', '1710000000'))->assertOk();

    expect($recipient->refresh()->delivery_status)->toBe('read');
});

test('webhook stores failure details', function () {
    config(['services.whatsapp.app_secret' => null]);
    $recipient = webhookRecipient(['delivery_status' => 'accepted']);

    postWebhook(webhookPayload('wamid.123', 'failed', '1710000000', [
        ['code' => 131026, 'title' => 'Message undeliverable'],
    ]))->assertOk();

    $recipient->refresh();

    expect($recipient->delivery_status)->toBe('failed')
        ->and($recipient->failure_code)->toBe('131026')
        ->and($recipient->failure_message)->toBe('Message undeliverable');
});

test('webhook ignores unknown message ids without crashing', function () {
    config(['services.whatsapp.app_secret' => null]);

    postWebhook(webhookPayload('unknown-message-id', 'sent', '1710000000'))->assertOk();

    expect(WebhookEvent::query()->count())->toBe(1);
});

test('webhook verifies signature when app secret is configured', function () {
    config(['services.whatsapp.app_secret' => 'app-secret']);
    webhookRecipient(['delivery_status' => 'accepted']);
    $payload = webhookPayload('wamid.123', 'sent', '1710000000');

    postWebhook($payload, 'wrong-secret')->assertUnauthorized();
    postWebhook($payload, 'app-secret')->assertOk();

    expect(CampaignRecipient::query()->firstOrFail()->delivery_status)->toBe('sent');
});

function webhookRecipient(array $overrides = []): CampaignRecipient
{
    $campaign = Campaign::factory()->create();

    return CampaignRecipient::query()->create(array_merge([
        'campaign_id' => $campaign->id,
        'source_row_number' => 2,
        'name' => 'Ayu',
        'phone_original' => '6281234567890',
        'phone_normalized' => '6281234567890',
        'row_data' => [],
        'validation_status' => CampaignRecipient::VALIDATION_VALID,
        'delivery_status' => CampaignRecipient::DELIVERY_ACCEPTED,
        'meta_message_id' => 'wamid.123',
        'attempt_count' => 1,
        'accepted_at' => now(),
    ], $overrides));
}

/**
 * @param  array<int, array<string, mixed>>  $errors
 * @return array<string, mixed>
 */
function webhookPayload(string $messageId, string $status, string $timestamp, array $errors = []): array
{
    $statusPayload = [
        'id' => $messageId,
        'status' => $status,
        'timestamp' => $timestamp,
    ];

    if ($errors !== []) {
        $statusPayload['errors'] = $errors;
    }

    return [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'waba-id',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'statuses' => [$statusPayload],
                ],
            ]],
        ]],
    ];
}

function postWebhook(array $payload, ?string $secret = null): TestResponse
{
    $content = json_encode($payload, JSON_THROW_ON_ERROR);
    $server = ['CONTENT_TYPE' => 'application/json'];

    if ($secret !== null) {
        $server['HTTP_X_HUB_SIGNATURE_256'] = 'sha256='.hash_hmac('sha256', $content, $secret);
    }

    return test()->call('POST', '/webhooks/meta/whatsapp', [], [], [], $server, $content);
}
