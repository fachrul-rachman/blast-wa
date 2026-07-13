<?php

use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

uses(DatabaseTransactions::class);

beforeEach(function () {
    CampaignRecipient::query()->delete();
    Campaign::query()->delete();
    WhatsappTemplate::query()->delete();

    config([
        'services.whatsapp.graph_api_base_url' => 'https://graph.example.test/v23.0',
        'services.whatsapp.business_account_id' => 'waba-123',
        'services.whatsapp.access_token' => 'secret-token',
    ]);

    actingAs(Admin::factory()->create());
});

test('approved supported templates are stored from meta', function () {
    Http::fake([
        'graph.example.test/v23.0/waba-123/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'tpl-1',
                    'name' => 'order_update',
                    'language' => 'id',
                    'category' => 'UTILITY',
                    'status' => 'APPROVED',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Halo {{1}}, pesanan {{2}} siap.'],
                    ],
                ],
            ],
        ]),
    ]);

    $this->post(route('templates.sync'))
        ->assertRedirect(route('templates.index'))
        ->assertSessionHas('status');

    $template = WhatsappTemplate::query()->where('meta_template_id', 'tpl-1')->firstOrFail();

    expect($template->is_supported)->toBeTrue()
        ->and($template->is_available)->toBeTrue()
        ->and($template->body_variables)->toBe(['1', '2']);
});

test('named body variables are extracted from meta templates', function () {
    Http::fake([
        'graph.example.test/v23.0/waba-123/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'tpl-named',
                    'name' => 'ticket_template',
                    'language' => 'id',
                    'category' => 'UTILITY',
                    'status' => 'APPROVED',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Nomor Tiket : *#{{nomor_ticket}}* Nama Customer : {{nama_customer}}.'],
                    ],
                ],
            ],
        ]),
    ]);

    $this->post(route('templates.sync'));

    expect(WhatsappTemplate::query()->where('meta_template_id', 'tpl-named')->firstOrFail()->body_variables)
        ->toBe(['nomor_ticket', 'nama_customer']);
});

test('named body variables prefer meta named parameter examples', function () {
    Http::fake([
        'graph.example.test/v23.0/waba-123/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'tpl-named-format',
                    'name' => 'ticket_template',
                    'language' => 'id',
                    'category' => 'UTILITY',
                    'status' => 'APPROVED',
                    'components' => [
                        [
                            'type' => 'BODY',
                            'text' => 'Halo {{1}}, nomor kamu {{2}}.',
                            'example' => [
                                'body_text_named_params' => [
                                    ['param_name' => 'nama_customer', 'example' => 'Ayu'],
                                    ['param_name' => 'nomor_wa', 'example' => '6281234567890'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $this->post(route('templates.sync'));

    expect(WhatsappTemplate::query()->where('meta_template_id', 'tpl-named-format')->firstOrFail()->body_variables)
        ->toBe(['nama_customer', 'nomor_wa']);
});

test('template sync follows meta pagination', function () {
    Http::fake([
        'graph.example.test/v23.0/waba-123/message_templates' => Http::response([
            'data' => [
                [
                    'id' => 'tpl-1',
                    'name' => 'first_template',
                    'language' => 'id',
                    'category' => 'UTILITY',
                    'status' => 'APPROVED',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Halo'],
                    ],
                ],
            ],
            'paging' => [
                'next' => 'https://graph.example.test/v23.0/waba-123/message_templates?after=next',
            ],
        ]),
        'graph.example.test/v23.0/waba-123/message_templates?after=next' => Http::response([
            'data' => [
                [
                    'id' => 'tpl-2',
                    'name' => 'second_template',
                    'language' => 'id',
                    'category' => 'MARKETING',
                    'status' => 'APPROVED',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Promo'],
                    ],
                ],
            ],
        ]),
    ]);

    $this->post(route('templates.sync'));

    expect(WhatsappTemplate::query()->where('is_available', true)->count())->toBe(2);
});

test('non approved and unsupported templates are unavailable for selection', function () {
    Http::fake([
        'graph.example.test/v23.0/waba-123/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'tpl-pending',
                    'name' => 'pending_template',
                    'language' => 'id',
                    'category' => 'UTILITY',
                    'status' => 'PENDING',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Halo'],
                    ],
                ],
                [
                    'id' => 'tpl-buttons',
                    'name' => 'button_template',
                    'language' => 'id',
                    'category' => 'UTILITY',
                    'status' => 'APPROVED',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Halo'],
                        ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Ya']]],
                    ],
                ],
            ],
        ]),
    ]);

    $this->post(route('templates.sync'));

    expect(WhatsappTemplate::query()->where('is_available', true)->count())->toBe(0)
        ->and(WhatsappTemplate::query()->where('is_supported', false)->count())->toBe(1);
});

test('approved templates with fixed image headers are available for selection', function () {
    Http::fake([
        'graph.example.test/v23.0/waba-123/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'tpl-image-header',
                    'name' => 'image_header_template',
                    'language' => 'id',
                    'category' => 'MARKETING',
                    'status' => 'APPROVED',
                    'components' => [
                        [
                            'type' => 'HEADER',
                            'format' => 'IMAGE',
                            'example' => [
                                'header_handle' => ['https://scontent.whatsapp.net/example.jpg'],
                            ],
                        ],
                        ['type' => 'BODY', 'text' => 'Halo {{nama_customer}}'],
                    ],
                ],
            ],
        ]),
    ]);

    $this->post(route('templates.sync'));

    $template = WhatsappTemplate::query()->where('meta_template_id', 'tpl-image-header')->firstOrFail();

    expect($template->is_supported)->toBeTrue()
        ->and($template->is_available)->toBeTrue()
        ->and($template->body_variables)->toBe(['nama_customer']);
});

test('existing templates are updated and missing templates are marked unavailable', function () {
    WhatsappTemplate::factory()->create([
        'meta_template_id' => 'old-template',
        'name' => 'old',
        'status' => 'APPROVED',
        'is_supported' => true,
        'is_available' => true,
    ]);

    Http::fake([
        'graph.example.test/v23.0/waba-123/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'new-template',
                    'name' => 'new',
                    'language' => 'id',
                    'category' => 'UTILITY',
                    'status' => 'APPROVED',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Halo'],
                    ],
                ],
            ],
        ]),
    ]);

    $this->post(route('templates.sync'));

    expect(WhatsappTemplate::query()->where('meta_template_id', 'old-template')->firstOrFail()->is_available)->toBeFalse()
        ->and(WhatsappTemplate::query()->where('meta_template_id', 'new-template')->firstOrFail()->is_available)->toBeTrue();
});

test('template sync reports api failures without exposing credentials', function () {
    Http::fake([
        'graph.example.test/v23.0/waba-123/message_templates*' => Http::response([
            'error' => ['message' => 'Invalid OAuth access token.'],
        ], 401),
    ]);

    $response = $this->post(route('templates.sync'));

    $response->assertSessionHasErrors('sync');

    expect((string) session('errors'))->not->toContain('secret-token');
});

test('template detail exposes components for image header inspection', function () {
    $template = WhatsappTemplate::factory()->create([
        'components' => [
            ['type' => 'HEADER', 'format' => 'IMAGE'],
            ['type' => 'BODY', 'text' => 'Halo {{nama_customer}}'],
        ],
        'body_variables' => ['nama_customer'],
        'is_supported' => false,
        'is_available' => false,
    ]);

    $this->get(route('templates.show', $template))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('templates/show')
            ->where('template.components.0.format', 'IMAGE')
            ->where('template.body_variables.0', 'nama_customer')
        );
});
