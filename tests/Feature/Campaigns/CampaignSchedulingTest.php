<?php

use App\Jobs\SendCampaignRecipientJob;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignVariableMapping;
use App\Models\MessageAttempt;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

uses(DatabaseTransactions::class);

beforeEach(function () {
    MessageAttempt::query()->delete();
    CampaignVariableMapping::query()->delete();
    CampaignRecipient::query()->delete();
    Campaign::query()->delete();
    WhatsappTemplate::query()->delete();

    actingAs(Admin::factory()->create());
});

test('admin can schedule a draft campaign for a future time', function () {
    Queue::fake();
    $campaign = schedulingCampaign();
    schedulingRecipient($campaign);
    $scheduledAt = now()->addHour();

    $this->post(route('campaigns.schedule.store', $campaign), [
        'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
        'consent_confirmed' => true,
    ])->assertRedirect(route('campaigns.show', $campaign));

    $campaign->refresh();

    expect($campaign->status)->toBe('scheduled')
        ->and($campaign->scheduled_at?->timestamp)->toBe($scheduledAt->timestamp)
        ->and($campaign->consent_confirmed_at)->not->toBeNull();

    Queue::assertNothingPushed();
});

test('scheduled time is parsed in application timezone', function () {
    config(['app.timezone' => 'Asia/Jakarta']);
    $campaign = schedulingCampaign();
    schedulingRecipient($campaign);
    $scheduledAt = now('Asia/Jakarta')->addDay()->setTime(13, 55);

    $this->post(route('campaigns.schedule.store', $campaign), [
        'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
        'consent_confirmed' => true,
    ])->assertRedirect(route('campaigns.show', $campaign));

    expect($campaign->refresh()->scheduled_at?->timezone('Asia/Jakarta')->format('H:i'))
        ->toBe('13:55');
});

test('scheduled campaign can be edited before processing and cancelled', function () {
    $campaign = schedulingCampaign([
        'status' => 'scheduled',
        'scheduled_at' => now()->addHour(),
        'consent_confirmed_at' => now(),
    ]);
    $newScheduledAt = now()->addHours(2);

    $this->patch(route('campaigns.schedule.update', $campaign), [
        'scheduled_at' => $newScheduledAt->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('campaigns.show', $campaign));

    expect($campaign->refresh()->scheduled_at?->timestamp)->toBe($newScheduledAt->timestamp);

    $this->delete(route('campaigns.schedule.destroy', $campaign))
        ->assertRedirect(route('campaigns.show', $campaign));

    $campaign->refresh();

    expect($campaign->status)->toBe('cancelled')
        ->and($campaign->cancelled_at)->not->toBeNull();
});

test('processing campaigns cannot be scheduled edited or cancelled', function () {
    $campaign = schedulingCampaign([
        'status' => 'processing',
        'scheduled_at' => now()->addHour(),
    ]);

    $this->patch(route('campaigns.schedule.update', $campaign), [
        'scheduled_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
    ])->assertSessionHasErrors('schedule');

    $this->delete(route('campaigns.schedule.destroy', $campaign))
        ->assertSessionHasErrors('schedule');
});

test('due scheduled campaigns are claimed and dispatched once', function () {
    Queue::fake();
    $campaign = schedulingCampaign([
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'consent_confirmed_at' => now()->subHour(),
    ]);
    schedulingRecipient($campaign);

    $this->artisan('campaigns:dispatch-due')->assertExitCode(0);
    $this->artisan('campaigns:dispatch-due')->assertExitCode(0);

    expect($campaign->refresh()->status)->toBe('processing');

    Queue::assertPushed(SendCampaignRecipientJob::class, 1);
});

test('future scheduled campaigns are not dispatched', function () {
    Queue::fake();
    $campaign = schedulingCampaign([
        'status' => 'scheduled',
        'scheduled_at' => now()->addHour(),
        'consent_confirmed_at' => now(),
    ]);
    schedulingRecipient($campaign);

    $this->artisan('campaigns:dispatch-due')->assertExitCode(0);

    expect($campaign->refresh()->status)->toBe('scheduled');

    Queue::assertNothingPushed();
});

function schedulingCampaign(array $overrides = []): Campaign
{
    $template = WhatsappTemplate::factory()->create([
        'name' => 'scheduled_template',
        'language_code' => 'id',
        'body_text' => 'Halo {{nama_customer}}.',
        'body_variables' => ['nama_customer'],
        'components' => [
            [
                'type' => 'BODY',
                'text' => 'Halo {{nama_customer}}.',
                'example' => [
                    'body_text_named_params' => [
                        ['param_name' => 'nama_customer', 'example' => 'Ayu'],
                    ],
                ],
            ],
        ],
    ]);

    $campaign = Campaign::factory()->create(array_merge([
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
    ], $overrides));

    CampaignVariableMapping::query()->create([
        'campaign_id' => $campaign->id,
        'variable' => 'nama_customer',
        'source_type' => 'column',
        'source_column_key' => 'nama_customer',
        'fixed_value' => null,
    ]);

    return $campaign;
}

function schedulingRecipient(Campaign $campaign): CampaignRecipient
{
    return CampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'source_row_number' => 2,
        'name' => 'Ayu',
        'phone_original' => '6281234567890',
        'phone_normalized' => '6281234567890',
        'row_data' => [
            'nama_customer' => 'Ayu',
            'nomor_wa' => '6281234567890',
        ],
        'validation_status' => CampaignRecipient::VALIDATION_VALID,
        'delivery_status' => CampaignRecipient::DELIVERY_PENDING,
    ]);
}
