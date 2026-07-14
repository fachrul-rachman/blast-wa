<?php

use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use function Pest\Laravel\actingAs;

uses(DatabaseTransactions::class);

beforeEach(function () {
    CampaignRecipient::query()->delete();
    Campaign::query()->delete();
    WhatsappTemplate::query()->delete();

    actingAs(Admin::factory()->create());
    Storage::fake('local');
});

test('admin can import a valid csv and see summary counts', function () {
    $campaign = Campaign::factory()->create();
    $file = uploadedCsv('recipients.csv', [
        ['Nama Customer', 'Nomor WA', 'Nomor Tiket'],
        ['Ayu', '6281234567890', 'T-001'],
        ['Budi', '6281234567891', 'T-002'],
    ]);

    $this->post(route('campaigns.import.store', $campaign), [
        'recipient_file' => $file,
    ])->assertRedirect(route('campaigns.show', $campaign));

    $campaign->refresh();

    expect($campaign->import_summary)
        ->toMatchArray([
            'total_rows' => 2,
            'valid_rows' => 2,
            'invalid_rows' => 0,
            'duplicate_rows' => 0,
            'send_eligible_rows' => 2,
            'phone_column_key' => 'nomor_wa',
            'name_column_key' => 'nama_customer',
        ])
        ->and(CampaignRecipient::query()->where('campaign_id', $campaign->id)->count())->toBe(2);
});

test('xlsx import must contain exactly one sheet', function () {
    $campaign = Campaign::factory()->create();

    $this->post(route('campaigns.import.store', $campaign), [
        'recipient_file' => uploadedXlsx('multi.xlsx', [
            'Sheet 1' => [
                ['Nama', 'Nomor WA'],
                ['Ayu', '6281234567890'],
            ],
            'Sheet 2' => [
                ['Nama', 'Nomor WA'],
                ['Budi', '6281234567891'],
            ],
        ]),
    ])->assertSessionHasErrors('recipient_file');
});

test('import rejects unsupported extension and oversized file', function () {
    $campaign = Campaign::factory()->create();

    $this->post(route('campaigns.import.store', $campaign), [
        'recipient_file' => UploadedFile::fake()->create('recipients.txt', 1, 'text/plain'),
    ])->assertSessionHasErrors('recipient_file');

    $this->post(route('campaigns.import.store', $campaign), [
        'recipient_file' => UploadedFile::fake()->create('recipients.csv', 5121, 'text/csv'),
    ])->assertSessionHasErrors('recipient_file');
});

test('phone validation rejects zero prefix plus prefix letters and duplicates', function () {
    $campaign = Campaign::factory()->create();
    $file = uploadedCsv('recipients.csv', [
        ['Nama', 'Whatsapp'],
        ['Valid', '6281234567890'],
        ['Zero', '081234567890'],
        ['Plus', '+6281234567891'],
        ['Letters', '62ABC'],
        ['Duplicate', '6281234567890'],
    ]);

    $this->post(route('campaigns.import.store', $campaign), [
        'recipient_file' => $file,
    ]);

    $campaign->refresh();

    expect($campaign->import_summary)
        ->toMatchArray([
            'total_rows' => 5,
            'valid_rows' => 0,
            'invalid_rows' => 3,
            'duplicate_rows' => 2,
            'send_eligible_rows' => 0,
        ]);

    expect(CampaignRecipient::query()->where('validation_status', 'invalid')->count())->toBe(3)
        ->and(CampaignRecipient::query()->where('validation_status', 'duplicate')->count())->toBe(2);
});

test('unique valid rows remain send eligible when other rows are duplicate', function () {
    $campaign = Campaign::factory()->create();
    $file = uploadedCsv('recipients.csv', [
        ['Nama', 'Whatsapp'],
        ['Valid', '6281234567890'],
        ['Duplicate A', '6281234567891'],
        ['Duplicate B', '6281234567891'],
    ]);

    $this->post(route('campaigns.import.store', $campaign), [
        'recipient_file' => $file,
    ])->assertRedirect(route('campaigns.show', $campaign));

    $campaign->refresh();

    expect($campaign->import_summary['valid_rows'])->toBe(1)
        ->and($campaign->import_summary['duplicate_rows'])->toBe(2)
        ->and($campaign->import_summary['send_eligible_rows'])->toBe(1);
});

test('admin can manually correct detected phone and name columns', function () {
    $campaign = Campaign::factory()->create();
    $file = uploadedCsv('recipients.csv', [
        ['Customer Label', 'Primary Contact'],
        ['Ayu', '6281234567890'],
    ]);

    $this->post(route('campaigns.import.store', $campaign), [
        'recipient_file' => $file,
        'phone_column_key' => 'primary_contact',
        'name_column_key' => 'customer_label',
    ])->assertRedirect(route('campaigns.show', $campaign));

    $recipient = CampaignRecipient::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($recipient->name)->toBe('Ayu')
        ->and($recipient->phone_normalized)->toBe('6281234567890')
        ->and($recipient->validation_status)->toBe('valid');
});

test('uploaded temporary file is removed after parsing', function () {
    $campaign = Campaign::factory()->create();
    $file = uploadedCsv('recipients.csv', [
        ['Nama Customer', 'Nomor WA'],
        ['Ayu', '6281234567890'],
    ]);
    $path = $file->getRealPath();

    expect(is_string($path) && is_file($path))->toBeTrue();

    $this->post(route('campaigns.import.store', $campaign), [
        'recipient_file' => $file,
    ])->assertRedirect(route('campaigns.show', $campaign));

    expect(is_string($path) && is_file($path))->toBeFalse();
});

test('admin can correct import mapping after rows are persisted', function () {
    $campaign = Campaign::factory()->create();
    $file = uploadedCsv('recipients.csv', [
        ['Nama', 'Nomor Lama', 'Nomor Baru'],
        ['Ayu', '081234567890', '6281234567890'],
    ]);

    $this->post(route('campaigns.import.store', $campaign), [
        'recipient_file' => $file,
        'phone_column_key' => 'nomor_lama',
        'name_column_key' => 'nama',
    ]);

    $this->patch(route('campaigns.import.mapping.update', $campaign), [
        'phone_column_key' => 'nomor_baru',
        'name_column_key' => 'nama',
    ])->assertRedirect(route('campaigns.show', $campaign));

    $recipient = CampaignRecipient::query()->where('campaign_id', $campaign->id)->firstOrFail();

    expect($recipient->phone_normalized)->toBe('6281234567890')
        ->and($recipient->validation_status)->toBe('valid');
});

test('files without usable headers and files over row limit are rejected', function () {
    $campaign = Campaign::factory()->create();

    $this->post(route('campaigns.import.store', $campaign), [
        'recipient_file' => uploadedCsv('empty-header.csv', [
            ['', ''],
            ['Ayu', '6281234567890'],
        ]),
    ])->assertSessionHasErrors('recipient_file');

    $rows = [['Nama', 'Nomor WA']];

    for ($index = 0; $index < 10001; $index++) {
        $rows[] = ['Ayu', '6281234567890'];
    }

    $this->post(route('campaigns.import.store', $campaign), [
        'recipient_file' => uploadedCsv('too-many.csv', $rows),
    ])->assertSessionHasErrors('recipient_file');
});

/**
 * @param  array<int, array<int, string|null>>  $rows
 */
function uploadedCsv(string $name, array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    $handle = fopen($path, 'wb');

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);

    return new UploadedFile($path, $name, 'text/csv', null, true);
}

/**
 * @param  array<string, array<int, array<int, string|null>>>  $sheets
 */
function uploadedXlsx(string $name, array $sheets): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheetIndex = 0;

    foreach ($sheets as $sheetName => $rows) {
        $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
        $sheet->setTitle($sheetName);
        $sheet->fromArray($rows);
        $sheetIndex++;
    }

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}
