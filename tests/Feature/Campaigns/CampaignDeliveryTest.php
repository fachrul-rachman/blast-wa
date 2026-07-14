<?php

use App\Jobs\SendCampaignRecipientJob;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignVariableMapping;
use App\Models\MessageAttempt;
use App\Models\WhatsappDeliveryQuotaUsage;
use App\Models\WhatsappTemplate;
use App\Services\Campaigns\DailyRecipientQuotaExceeded;
use App\Services\Campaigns\MessageDeliveryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

uses(DatabaseTransactions::class);

beforeEach(function () {
    MessageAttempt::query()->delete();
    WhatsappDeliveryQuotaUsage::query()->delete();
    CampaignVariableMapping::query()->delete();
    CampaignRecipient::query()->delete();
    Campaign::query()->delete();
    WhatsappTemplate::query()->delete();
    Cache::flush();

    actingAs(Admin::factory()->create());
});

test('admin can start immediate send for eligible recipients only', function () {
    Queue::fake();

    $campaign = deliveryCampaign();
    $eligible = deliveryRecipient($campaign, 2, 'valid', 'pending', '6281234567890');
    deliveryRecipient($campaign, 3, 'invalid', 'pending', '6281234567891');
    deliveryRecipient($campaign, 4, 'duplicate', 'pending', '6281234567892');
    deliveryRecipient($campaign, 5, 'missing_data', 'pending', '6281234567893');

    $this->post(route('campaigns.send.store', $campaign), [
        'consent_confirmed' => true,
    ])->assertRedirect(route('campaigns.show', $campaign));

    $campaign->refresh();
    $eligible->refresh();

    expect($campaign->status)->toBe('processing')
        ->and($campaign->consent_confirmed_at)->not->toBeNull()
        ->and($eligible->delivery_status)->toBe('queued');

    expect(CampaignRecipient::query()
        ->where('campaign_id', $campaign->id)
        ->where('validation_status', '!=', 'valid')
        ->pluck('delivery_status')
        ->all())->toBe(['skipped', 'skipped', 'skipped']);

    Queue::assertPushed(SendCampaignRecipientJob::class, 1);
});

test('recipient job sends mapped template payload and stores accepted attempt', function () {
    config([
        'services.whatsapp.graph_api_base_url' => 'https://graph.facebook.com/v23.0',
        'services.whatsapp.phone_number_id' => 'phone-id',
        'services.whatsapp.access_token' => 'secret-token',
    ]);

    Http::fake([
        'graph.facebook.com/v23.0/phone-id/messages' => Http::response([
            'messages' => [
                ['id' => 'wamid.123'],
            ],
        ]),
    ]);

    $campaign = deliveryCampaign();
    $recipient = deliveryRecipient($campaign, 2, 'valid', 'queued', '6281234567890');

    (new SendCampaignRecipientJob($recipient->id))->handle(app(MessageDeliveryService::class));

    $recipient->refresh();

    expect($recipient->delivery_status)->toBe('accepted')
        ->and($recipient->meta_message_id)->toBe('wamid.123')
        ->and($recipient->attempt_count)->toBe(1)
        ->and(MessageAttempt::query()->where('campaign_recipient_id', $recipient->id)->count())->toBe(1);

    Http::assertSent(fn ($request) => $request->data()['to'] === '6281234567890'
        && $request->data()['template']['name'] === 'ticket_template'
        && $request->data()['template']['components'][0]['parameters'][0]['parameter_name'] === 'nomor_ticket'
        && $request->data()['template']['components'][0]['parameters'][0]['text'] === 'T-001'
        && $request->data()['template']['components'][0]['parameters'][1]['parameter_name'] === 'nama_customer'
        && $request->data()['template']['components'][0]['parameters'][1]['text'] === 'Fixed Customer'
    );
});

test('recipient job sends image header media parameter when template has image header', function () {
    config([
        'services.whatsapp.graph_api_base_url' => 'https://graph.facebook.com/v23.0',
        'services.whatsapp.phone_number_id' => 'phone-id',
        'services.whatsapp.access_token' => 'secret-token',
    ]);

    Http::fake([
        'scontent.whatsapp.net/example.jpg' => Http::response('fake-image-content', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
        'graph.facebook.com/v23.0/phone-id/media' => Http::response([
            'id' => 'media-id-123',
        ]),
        'graph.facebook.com/v23.0/phone-id/messages' => Http::response([
            'messages' => [
                ['id' => 'wamid.image'],
            ],
        ]),
    ]);

    $template = WhatsappTemplate::factory()->create([
        'name' => 'image_header_template',
        'language_code' => 'id',
        'body_text' => 'Halo customer.',
        'body_variables' => [],
        'components' => [
            [
                'type' => 'HEADER',
                'format' => 'IMAGE',
                'example' => [
                    'header_handle' => ['https://scontent.whatsapp.net/example.jpg'],
                ],
            ],
            ['type' => 'BODY', 'text' => 'Halo customer.'],
        ],
    ]);

    $campaign = Campaign::factory()->create([
        'whatsapp_template_id' => $template->id,
        'template_snapshot' => [
            'id' => $template->id,
            'meta_template_id' => $template->meta_template_id,
            'name' => $template->name,
            'language_code' => $template->language_code,
            'category' => $template->category,
            'body_text' => $template->body_text,
            'body_variables' => $template->body_variables,
            'components' => $template->components,
        ],
        'status' => Campaign::STATUS_PROCESSING,
    ]);
    $recipient = deliveryRecipient($campaign, 2, 'valid', 'queued', '6281234567890');

    (new SendCampaignRecipientJob($recipient->id))->handle(app(MessageDeliveryService::class));

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/messages')
        && $request->data()['template']['components'][0] === [
            'type' => 'header',
            'parameters' => [[
                'type' => 'image',
                'image' => [
                    'id' => 'media-id-123',
                ],
            ]],
        ]);

    Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v23.0/phone-id/media');
});

test('duplicate job execution does not resend an accepted recipient', function () {
    config([
        'services.whatsapp.graph_api_base_url' => 'https://graph.facebook.com/v23.0',
        'services.whatsapp.phone_number_id' => 'phone-id',
        'services.whatsapp.access_token' => 'secret-token',
    ]);

    Http::fake([
        'graph.facebook.com/v23.0/phone-id/messages' => Http::response([
            'messages' => [
                ['id' => 'wamid.123'],
            ],
        ]),
    ]);

    $campaign = deliveryCampaign();
    $recipient = deliveryRecipient($campaign, 2, 'valid', 'queued', '6281234567890');
    $service = app(MessageDeliveryService::class);

    (new SendCampaignRecipientJob($recipient->id))->handle($service);
    (new SendCampaignRecipientJob($recipient->id))->handle($service);

    expect($recipient->refresh()->attempt_count)->toBe(1)
        ->and(MessageAttempt::query()->where('campaign_recipient_id', $recipient->id)->count())->toBe(1);

    Http::assertSentCount(1);
});

test('recipient delivery is delayed when daily unique recipient quota is exhausted', function () {
    config([
        'services.whatsapp.daily_unique_recipient_limit' => 1,
        'services.whatsapp.daily_unique_recipient_limit_enabled' => true,
        'services.whatsapp.graph_api_base_url' => 'https://graph.facebook.com/v23.0',
        'services.whatsapp.phone_number_id' => 'phone-id',
        'services.whatsapp.access_token' => 'secret-token',
    ]);

    WhatsappDeliveryQuotaUsage::query()->create([
        'phone_normalized' => '6281234567899',
        'campaign_recipient_id' => null,
        'accepted_at' => now()->subMinute(),
    ]);

    Http::fake();

    $campaign = deliveryCampaign();
    $recipient = deliveryRecipient($campaign, 2, 'valid', 'queued', '6281234567890');

    try {
        app(MessageDeliveryService::class)->deliver($recipient->id);
        $this->fail('Expected daily quota exception.');
    } catch (DailyRecipientQuotaExceeded $exception) {
        expect($exception->delaySeconds)->toBeGreaterThan(0);
    }

    expect($recipient->refresh()->delivery_status)->toBe(CampaignRecipient::DELIVERY_QUEUED)
        ->and($recipient->attempt_count)->toBe(0);

    Http::assertNothingSent();
});

function deliveryCampaign(): Campaign
{
    $template = WhatsappTemplate::factory()->create([
        'name' => 'ticket_template',
        'language_code' => 'id',
        'body_text' => 'Nomor Tiket : *#{{nomor_ticket}}* Nama Customer : {{nama_customer}}.',
        'body_variables' => ['nomor_ticket', 'nama_customer'],
        'components' => [
            [
                'type' => 'BODY',
                'text' => 'Nomor Tiket : *#{{nomor_ticket}}* Nama Customer : {{nama_customer}}.',
                'example' => [
                    'body_text_named_params' => [
                        ['param_name' => 'nomor_ticket', 'example' => 'T-001'],
                        ['param_name' => 'nama_customer', 'example' => 'Ayu'],
                    ],
                ],
            ],
        ],
    ]);

    $campaign = Campaign::factory()->create([
        'whatsapp_template_id' => $template->id,
        'template_snapshot' => [
            'id' => $template->id,
            'meta_template_id' => $template->meta_template_id,
            'name' => $template->name,
            'language_code' => $template->language_code,
            'category' => $template->category,
            'body_text' => $template->body_text,
            'body_variables' => $template->body_variables,
            'components' => $template->components,
        ],
        'import_summary' => [
            'headers' => [
                ['key' => 'nama_customer', 'label' => 'Nama Customer'],
                ['key' => 'nomor_wa', 'label' => 'Nomor WA'],
                ['key' => 'nomor_ticket', 'label' => 'Nomor Tiket'],
            ],
            'total_rows' => 1,
            'valid_rows' => 1,
            'invalid_rows' => 0,
            'duplicate_rows' => 0,
            'missing_data_rows' => 0,
            'skipped_rows' => 0,
            'send_eligible_rows' => 1,
            'phone_column_key' => 'nomor_wa',
            'name_column_key' => 'nama_customer',
        ],
    ]);

    CampaignVariableMapping::query()->create([
        'campaign_id' => $campaign->id,
        'variable' => 'nomor_ticket',
        'source_type' => 'column',
        'source_column_key' => 'nomor_ticket',
        'fixed_value' => null,
    ]);

    CampaignVariableMapping::query()->create([
        'campaign_id' => $campaign->id,
        'variable' => 'nama_customer',
        'source_type' => 'fixed',
        'source_column_key' => null,
        'fixed_value' => 'Fixed Customer',
    ]);

    return $campaign;
}

function deliveryRecipient(
    Campaign $campaign,
    int $rowNumber,
    string $validationStatus,
    string $deliveryStatus,
    string $phone,
): CampaignRecipient {
    return CampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'source_row_number' => $rowNumber,
        'name' => 'Ayu',
        'phone_original' => $phone,
        'phone_normalized' => $phone,
        'row_data' => [
            'nama_customer' => 'Ayu',
            'nomor_wa' => $phone,
            'nomor_ticket' => 'T-001',
        ],
        'validation_status' => $validationStatus,
        'delivery_status' => $deliveryStatus,
    ]);
}
