<?php

use App\Jobs\SendCampaignRecipientJob;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

uses(DatabaseTransactions::class);

beforeEach(function () {
    CampaignRecipient::query()->delete();
    Campaign::query()->delete();
    WhatsappTemplate::query()->delete();

    actingAs(Admin::factory()->create());
});

test('campaign list can be searched and filtered', function () {
    $ticketTemplate = WhatsappTemplate::factory()->create(['name' => 'ticket_template']);
    $promoTemplate = WhatsappTemplate::factory()->create(['name' => 'promo_template']);

    Campaign::factory()->create([
        'title' => 'Ticket Closing July',
        'whatsapp_template_id' => $ticketTemplate->id,
        'status' => Campaign::STATUS_COMPLETED,
        'created_at' => now()->subDay(),
    ]);
    Campaign::factory()->create([
        'title' => 'Promo Blast',
        'whatsapp_template_id' => $promoTemplate->id,
        'status' => Campaign::STATUS_DRAFT,
        'created_at' => now()->subDays(10),
    ]);

    $this->get(route('campaigns.index', [
        'search' => 'ticket',
        'status' => Campaign::STATUS_COMPLETED,
        'template_id' => $ticketTemplate->id,
        'date_from' => now()->subDays(2)->format('Y-m-d'),
        'date_to' => now()->format('Y-m-d'),
    ]))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('campaigns.data', 1)
            ->where('campaigns.data.0.title', 'Ticket Closing July')
            ->where('filters.search', 'ticket')
        );
});

test('campaign detail exposes delivery summary and paginated recipients', function () {
    $campaign = historyCampaign(['status' => Campaign::STATUS_COMPLETED]);

    foreach ([
        CampaignRecipient::DELIVERY_ACCEPTED,
        CampaignRecipient::DELIVERY_SENT,
        CampaignRecipient::DELIVERY_DELIVERED,
        CampaignRecipient::DELIVERY_READ,
        CampaignRecipient::DELIVERY_FAILED,
        CampaignRecipient::DELIVERY_SKIPPED,
    ] as $index => $status) {
        historyRecipient($campaign, $index + 2, $status);
    }

    $this->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('delivery_summary.accepted', 1)
            ->where('delivery_summary.sent', 1)
            ->where('delivery_summary.delivered', 1)
            ->where('delivery_summary.read', 1)
            ->where('delivery_summary.failed', 1)
            ->where('delivery_summary.skipped', 1)
            ->has('recipients.data', 6)
        );
});

test('retry failed recipients queues only failed rows and never successful rows', function () {
    Queue::fake();
    $campaign = historyCampaign(['status' => Campaign::STATUS_COMPLETED]);
    $failed = historyRecipient($campaign, 2, CampaignRecipient::DELIVERY_FAILED, [
        'attempt_count' => 1,
        'failed_at' => now(),
        'failure_code' => '500',
        'failure_message' => 'Temporary failure',
    ]);
    historyRecipient($campaign, 3, CampaignRecipient::DELIVERY_ACCEPTED, [
        'meta_message_id' => 'wamid.success',
        'accepted_at' => now(),
    ]);

    $this->post(route('campaigns.retry-failed.store', $campaign))
        ->assertRedirect(route('campaigns.show', $campaign));

    $campaign->refresh();
    $failed->refresh();

    expect($campaign->status)->toBe(Campaign::STATUS_PROCESSING)
        ->and($failed->delivery_status)->toBe(CampaignRecipient::DELIVERY_QUEUED)
        ->and($failed->meta_message_id)->toBeNull()
        ->and($failed->failed_at)->toBeNull();

    Queue::assertPushed(SendCampaignRecipientJob::class, 1);
});

test('retry failed is unavailable when no failed recipients exist', function () {
    Queue::fake();
    $campaign = historyCampaign(['status' => Campaign::STATUS_COMPLETED]);
    historyRecipient($campaign, 2, CampaignRecipient::DELIVERY_ACCEPTED, [
        'meta_message_id' => 'wamid.success',
        'accepted_at' => now(),
    ]);

    $this->post(route('campaigns.retry-failed.store', $campaign))
        ->assertSessionHasErrors('retry');

    Queue::assertNothingPushed();
});

function historyCampaign(array $overrides = []): Campaign
{
    $template = WhatsappTemplate::factory()->create([
        'name' => 'history_template',
        'language_code' => 'id',
        'body_text' => 'Halo',
        'body_variables' => [],
        'components' => [
            ['type' => 'BODY', 'text' => 'Halo'],
        ],
    ]);

    return Campaign::factory()->create(array_merge([
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
            'headers' => [],
            'total_rows' => 1,
            'valid_rows' => 1,
            'invalid_rows' => 0,
            'duplicate_rows' => 0,
            'missing_data_rows' => 0,
            'skipped_rows' => 0,
            'send_eligible_rows' => 1,
        ],
    ], $overrides));
}

function historyRecipient(Campaign $campaign, int $rowNumber, string $deliveryStatus, array $overrides = []): CampaignRecipient
{
    return CampaignRecipient::query()->create(array_merge([
        'campaign_id' => $campaign->id,
        'source_row_number' => $rowNumber,
        'name' => 'Ayu',
        'phone_original' => '62812345678'.$rowNumber,
        'phone_normalized' => '62812345678'.$rowNumber,
        'row_data' => [],
        'validation_status' => CampaignRecipient::VALIDATION_VALID,
        'delivery_status' => $deliveryStatus,
    ], $overrides));
}
