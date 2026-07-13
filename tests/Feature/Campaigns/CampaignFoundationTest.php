<?php

use App\Models\Admin;
use App\Models\Campaign;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use function Pest\Laravel\actingAs;

uses(DatabaseTransactions::class);

beforeEach(function () {
    Campaign::query()->delete();
    WhatsappTemplate::query()->delete();

    actingAs(Admin::factory()->create());
});

test('guest cannot access campaign pages', function () {
    auth()->logout();

    $this->get(route('campaigns.index'))->assertRedirect(route('login'));
    $this->get(route('campaigns.create'))->assertRedirect(route('login'));
});

test('admin can create a draft campaign from an available template', function () {
    $template = WhatsappTemplate::factory()->create([
        'name' => 'ticket_template',
        'language_code' => 'id',
        'body_text' => 'Nomor Tiket : *#{{nomor_ticket}}* Nama Customer : {{nama_customer}}.',
        'body_variables' => ['nomor_ticket', 'nama_customer'],
        'components' => [
            ['type' => 'BODY', 'text' => 'Nomor Tiket : *#{{nomor_ticket}}* Nama Customer : {{nama_customer}}.'],
        ],
        'is_supported' => true,
        'is_available' => true,
    ]);

    $this->post(route('campaigns.store'), [
        'title' => 'Campaign Tiket Juli',
        'whatsapp_template_id' => $template->id,
    ])->assertRedirect();

    $campaign = Campaign::query()->where('title', 'Campaign Tiket Juli')->firstOrFail();

    expect($campaign->status)->toBe('draft')
        ->and($campaign->whatsapp_template_id)->toBe($template->id)
        ->and($campaign->template_snapshot['name'])->toBe('ticket_template')
        ->and($campaign->template_snapshot['body_variables'])->toBe(['nomor_ticket', 'nama_customer']);
});

test('campaign requires a title and selectable template', function () {
    $unavailableTemplate = WhatsappTemplate::factory()->create([
        'is_supported' => false,
        'is_available' => false,
    ]);

    $this->post(route('campaigns.store'), [
        'title' => '',
        'whatsapp_template_id' => $unavailableTemplate->id,
    ])->assertSessionHasErrors(['title', 'whatsapp_template_id']);
});

test('campaign list and detail are available to admin', function () {
    $campaign = Campaign::factory()->create([
        'title' => 'Draft Promo',
        'status' => 'draft',
    ]);

    $this->get(route('campaigns.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('campaigns/index')
            ->where('campaigns.data.0.title', 'Draft Promo')
        );

    $this->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('campaigns/show')
            ->where('campaign.title', 'Draft Promo')
            ->where('campaign.status', 'draft')
        );
});

test('campaign create page only lists available supported templates', function () {
    $availableTemplate = WhatsappTemplate::factory()->create([
        'name' => 'available_template',
        'is_supported' => true,
        'is_available' => true,
    ]);

    WhatsappTemplate::factory()->create([
        'name' => 'hidden_template',
        'is_supported' => false,
        'is_available' => false,
    ]);

    $this->get(route('campaigns.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('campaigns/create')
            ->where('templates.0.id', $availableTemplate->id)
            ->where('templates.0.name', 'available_template')
            ->has('templates', 1)
        );
});
