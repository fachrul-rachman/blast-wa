<?php

use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignVariableMapping;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use function Pest\Laravel\actingAs;

uses(DatabaseTransactions::class);

beforeEach(function () {
    CampaignVariableMapping::query()->delete();
    CampaignRecipient::query()->delete();
    Campaign::query()->delete();
    WhatsappTemplate::query()->delete();

    actingAs(Admin::factory()->create());
});

test('admin can select exactly one duplicate winner', function () {
    $campaign = campaignWithTicketTemplate();
    $first = duplicateRecipient($campaign, 2, 'Ayu', 'T-001');
    $second = duplicateRecipient($campaign, 3, 'Ayu Updated', 'T-002');

    $campaign->update([
        'import_summary' => [
            'headers' => headers(),
            'total_rows' => 2,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'duplicate_rows' => 2,
            'missing_data_rows' => 0,
            'skipped_rows' => 0,
            'send_eligible_rows' => 0,
            'phone_column_key' => 'nomor_wa',
            'name_column_key' => 'nama_customer',
        ],
    ]);

    $this->patch(route('campaigns.duplicates.update', $campaign), [
        'duplicate_group_key' => '6281234567890',
        'winner_id' => $second->id,
    ])->assertRedirect(route('campaigns.show', $campaign));

    $first->refresh();
    $second->refresh();
    $campaign->refresh();

    expect($first->validation_status)->toBe('skipped')
        ->and($first->is_duplicate_winner)->toBeFalse()
        ->and($second->validation_status)->toBe('valid')
        ->and($second->is_duplicate_winner)->toBeTrue()
        ->and($campaign->import_summary['send_eligible_rows'])->toBe(1);
});

test('admin can save variable mappings and preview rendered messages', function () {
    $campaign = campaignWithTicketTemplate();
    validRecipient($campaign, 2, 'Ayu', 'T-001');

    $this->patch(route('campaigns.variable-mappings.update', $campaign), [
        'mappings' => [
            [
                'variable' => 'nomor_ticket',
                'source_type' => 'column',
                'source_column_key' => 'nomor_ticket',
                'fixed_value' => null,
            ],
            [
                'variable' => 'nama_customer',
                'source_type' => 'fixed',
                'source_column_key' => null,
                'fixed_value' => 'Customer VIP',
            ],
        ],
    ])->assertRedirect(route('campaigns.show', $campaign));

    expect(CampaignVariableMapping::query()->where('campaign_id', $campaign->id)->count())->toBe(2);

    $this->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('previews.0.rendered_body', 'Nomor Tiket : *#T-001* Nama Customer : Customer VIP.')
            ->where('previews.0.resolved_values.nomor_ticket', 'T-001')
        );
});

test('missing mapped column values mark recipients as missing data', function () {
    $campaign = campaignWithTicketTemplate();
    validRecipient($campaign, 2, 'Ayu', null);

    $this->patch(route('campaigns.variable-mappings.update', $campaign), [
        'mappings' => [
            [
                'variable' => 'nomor_ticket',
                'source_type' => 'column',
                'source_column_key' => 'nomor_ticket',
                'fixed_value' => null,
            ],
            [
                'variable' => 'nama_customer',
                'source_type' => 'column',
                'source_column_key' => 'nama_customer',
                'fixed_value' => null,
            ],
        ],
    ]);

    $campaign->refresh();

    expect(CampaignRecipient::query()->firstOrFail()->validation_status)->toBe('missing_data')
        ->and($campaign->import_summary['missing_data_rows'])->toBe(1)
        ->and($campaign->import_summary['send_eligible_rows'])->toBe(0);
});

test('fixed value mappings cannot be empty', function () {
    $campaign = campaignWithTicketTemplate();
    validRecipient($campaign, 2, 'Ayu', 'T-001');

    $this->patch(route('campaigns.variable-mappings.update', $campaign), [
        'mappings' => [
            [
                'variable' => 'nomor_ticket',
                'source_type' => 'fixed',
                'source_column_key' => null,
                'fixed_value' => ' ',
            ],
            [
                'variable' => 'nama_customer',
                'source_type' => 'column',
                'source_column_key' => 'nama_customer',
                'fixed_value' => null,
            ],
        ],
    ])->assertSessionHasErrors('mappings');
});

function campaignWithTicketTemplate(): Campaign
{
    $template = WhatsappTemplate::factory()->create([
        'body_text' => 'Nomor Tiket : *#{{nomor_ticket}}* Nama Customer : {{nama_customer}}.',
        'body_variables' => ['nomor_ticket', 'nama_customer'],
        'components' => [
            ['type' => 'BODY', 'text' => 'Nomor Tiket : *#{{nomor_ticket}}* Nama Customer : {{nama_customer}}.'],
        ],
    ]);

    return Campaign::factory()->create([
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
            'headers' => headers(),
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
}

function validRecipient(Campaign $campaign, int $rowNumber, string $name, ?string $ticket): CampaignRecipient
{
    return CampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'source_row_number' => $rowNumber,
        'name' => $name,
        'phone_original' => '6281234567890',
        'phone_normalized' => '6281234567890',
        'row_data' => [
            'nama_customer' => $name,
            'nomor_wa' => '6281234567890',
            'nomor_ticket' => $ticket,
        ],
        'validation_status' => 'valid',
        'delivery_status' => 'pending',
    ]);
}

function duplicateRecipient(Campaign $campaign, int $rowNumber, string $name, string $ticket): CampaignRecipient
{
    return CampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'source_row_number' => $rowNumber,
        'name' => $name,
        'phone_original' => '6281234567890',
        'phone_normalized' => '6281234567890',
        'row_data' => [
            'nama_customer' => $name,
            'nomor_wa' => '6281234567890',
            'nomor_ticket' => $ticket,
        ],
        'validation_status' => 'duplicate',
        'duplicate_group_key' => '6281234567890',
        'delivery_status' => 'pending',
    ]);
}

/**
 * @return array<int, array{key: string, label: string}>
 */
function headers(): array
{
    return [
        ['key' => 'nama_customer', 'label' => 'Nama Customer'],
        ['key' => 'nomor_wa', 'label' => 'Nomor WA'],
        ['key' => 'nomor_ticket', 'label' => 'Nomor Tiket'],
    ];
}
