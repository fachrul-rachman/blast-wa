<?php

use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(DatabaseTransactions::class);

beforeEach(function () {
    CampaignRecipient::query()->delete();
    Campaign::query()->delete();
    WhatsappTemplate::query()->delete();

    actingAs(Admin::factory()->create());
    Storage::fake('local');
});

test('locked campaigns cannot import recipients', function () {
    $campaign = Campaign::factory()->create([
        'status' => Campaign::STATUS_COMPLETED,
    ]);

    $this->post(route('campaigns.import.store', $campaign), [
        'recipient_file' => guardUploadedCsv('recipients.csv', [
            ['Nama Customer', 'Nomor WA'],
            ['Ayu', '6281234567890'],
        ]),
    ])->assertSessionHasErrors('campaign');

    expect(CampaignRecipient::query()->where('campaign_id', $campaign->id)->count())->toBe(0);
});

test('locked campaigns cannot update recipient mapping', function () {
    $campaign = guardedCampaign(Campaign::STATUS_SCHEDULED);
    $recipient = guardedRecipient($campaign, '081234567890', CampaignRecipient::VALIDATION_INVALID);

    $this->patch(route('campaigns.import.mapping.update', $campaign), [
        'phone_column_key' => 'nomor_baru',
        'name_column_key' => 'nama_customer',
    ])->assertSessionHasErrors('campaign');

    $recipient->refresh();

    expect($recipient->phone_normalized)->toBeNull()
        ->and($recipient->validation_status)->toBe(CampaignRecipient::VALIDATION_INVALID);
});

test('locked campaigns cannot update duplicates or variable mappings', function () {
    $campaign = guardedCampaign(Campaign::STATUS_PROCESSING);
    $first = duplicateGuardRecipient($campaign, 2);
    $second = duplicateGuardRecipient($campaign, 3);

    $this->patch(route('campaigns.duplicates.update', $campaign), [
        'duplicate_group_key' => '6281234567890',
        'winner_id' => $second->id,
    ])->assertSessionHasErrors('winner_id');

    $this->patch(route('campaigns.variable-mappings.update', $campaign), [
        'mappings' => [
            [
                'variable' => 'nomor_ticket',
                'source_type' => 'column',
                'source_column_key' => 'nomor_ticket',
                'fixed_value' => null,
            ],
        ],
    ])->assertSessionHasErrors('mappings');

    $first->refresh();
    $second->refresh();

    expect($first->validation_status)->toBe(CampaignRecipient::VALIDATION_DUPLICATE)
        ->and($second->validation_status)->toBe(CampaignRecipient::VALIDATION_DUPLICATE)
        ->and($campaign->variableMappings()->count())->toBe(0);
});

function guardedCampaign(string $status): Campaign
{
    $template = WhatsappTemplate::factory()->create([
        'body_text' => 'Nomor Tiket : *#{{nomor_ticket}}*',
        'body_variables' => ['nomor_ticket'],
    ]);

    return Campaign::factory()->create([
        'whatsapp_template_id' => $template->id,
        'template_snapshot' => [
            'id' => $template->id,
            'name' => $template->name,
            'language_code' => $template->language_code,
            'body_text' => $template->body_text,
            'body_variables' => $template->body_variables,
        ],
        'status' => $status,
        'import_summary' => [
            'headers' => [
                ['key' => 'nama_customer', 'label' => 'Nama Customer'],
                ['key' => 'nomor_lama', 'label' => 'Nomor Lama'],
                ['key' => 'nomor_baru', 'label' => 'Nomor Baru'],
                ['key' => 'nomor_ticket', 'label' => 'Nomor Tiket'],
            ],
            'total_rows' => 1,
            'valid_rows' => 0,
            'invalid_rows' => 1,
            'duplicate_rows' => 0,
            'missing_data_rows' => 0,
            'skipped_rows' => 0,
            'send_eligible_rows' => 0,
            'phone_column_key' => 'nomor_lama',
            'name_column_key' => 'nama_customer',
        ],
    ]);
}

function guardedRecipient(Campaign $campaign, string $phone, string $status): CampaignRecipient
{
    return CampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'source_row_number' => 2,
        'name' => 'Ayu',
        'phone_original' => $phone,
        'phone_normalized' => null,
        'row_data' => [
            'nama_customer' => 'Ayu',
            'nomor_lama' => $phone,
            'nomor_baru' => '6281234567890',
            'nomor_ticket' => 'T-001',
        ],
        'validation_status' => $status,
        'delivery_status' => 'pending',
    ]);
}

function duplicateGuardRecipient(Campaign $campaign, int $rowNumber): CampaignRecipient
{
    return CampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'source_row_number' => $rowNumber,
        'name' => 'Ayu',
        'phone_original' => '6281234567890',
        'phone_normalized' => '6281234567890',
        'row_data' => [
            'nama_customer' => 'Ayu',
            'nomor_ticket' => 'T-001',
        ],
        'validation_status' => CampaignRecipient::VALIDATION_DUPLICATE,
        'duplicate_group_key' => '6281234567890',
        'delivery_status' => 'pending',
    ]);
}

/**
 * @param  array<int, array<int, string|null>>  $rows
 */
function guardUploadedCsv(string $name, array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    $handle = fopen($path, 'wb');

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);

    return new UploadedFile($path, $name, 'text/csv', null, true);
}
