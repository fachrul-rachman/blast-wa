<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\WhatsappTemplate;
use App\Services\Campaigns\CampaignDeliverySummaryService;
use App\Services\Campaigns\CampaignPreviewService;
use App\Services\Campaigns\WhatsappDailyRecipientQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
            'template_id' => ['nullable', 'integer', 'exists:whatsapp_templates,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $campaigns = Campaign::query()
            ->with('whatsappTemplate:id,name,language_code')
            ->when(filled($filters['search'] ?? null), fn ($query) => $query->where('title', 'ilike', '%'.$filters['search'].'%'))
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['template_id'] ?? null), fn ($query) => $query->where('whatsapp_template_id', $filters['template_id']))
            ->when(filled($filters['date_from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('campaigns/index', [
            'campaigns' => $campaigns->through(
                fn (Campaign $campaign): array => [
                    'id' => $campaign->id,
                    'title' => $campaign->title,
                    'status' => $campaign->status,
                    'template_name' => $campaign->whatsappTemplate->name,
                    'created_at' => $campaign->created_at?->toISOString(),
                ],
            ),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'template_id' => isset($filters['template_id']) ? (string) $filters['template_id'] : '',
                'date_from' => (string) ($filters['date_from'] ?? ''),
                'date_to' => (string) ($filters['date_to'] ?? ''),
            ],
            'templates' => WhatsappTemplate::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (WhatsappTemplate $template): array => [
                    'id' => $template->id,
                    'name' => $template->name,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('campaigns/create', [
            'templates' => WhatsappTemplate::query()
                ->where('is_available', true)
                ->where('is_supported', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'language_code',
                    'category',
                    'body_variables',
                    'body_text',
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'whatsapp_template_id' => [
                'required',
                'integer',
                Rule::exists('whatsapp_templates', 'id')->where('is_available', true)->where('is_supported', true),
            ],
        ]);

        $template = WhatsappTemplate::query()
            ->whereKey($validated['whatsapp_template_id'])
            ->firstOrFail();

        $campaign = Campaign::query()->create([
            'title' => $validated['title'],
            'whatsapp_template_id' => $template->id,
            'template_snapshot' => $this->snapshot($template),
            'status' => Campaign::STATUS_DRAFT,
        ]);

        return redirect()->route('campaigns.show', $campaign);
    }

    public function show(
        Campaign $campaign,
        CampaignPreviewService $previewService,
        CampaignDeliverySummaryService $summaryService,
        WhatsappDailyRecipientQuota $recipientQuota,
    ): Response {
        $campaign->load('whatsappTemplate:id,name,language_code');

        return Inertia::render('campaigns/show', [
            'campaign' => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'status' => $campaign->status,
                'template_name' => $campaign->whatsappTemplate->name,
                'template_language' => $campaign->whatsappTemplate->language_code,
                'template_snapshot' => $campaign->template_snapshot,
                'import_summary' => $campaign->import_summary,
                'created_at' => $campaign->created_at?->toISOString(),
                'scheduled_at' => $campaign->scheduled_at?->toISOString(),
                'started_at' => $campaign->started_at?->toISOString(),
                'completed_at' => $campaign->completed_at?->toISOString(),
                'cancelled_at' => $campaign->cancelled_at?->toISOString(),
            ],
            'recipients' => $campaign->recipients()
                ->where('validation_status', CampaignRecipient::VALIDATION_VALID)
                ->orderBy('source_row_number')
                ->paginate(50, [
                    'id',
                    'source_row_number',
                    'name',
                    'phone_original',
                    'phone_normalized',
                    'validation_status',
                    'validation_errors',
                    'duplicate_group_key',
                    'delivery_status',
                    'meta_message_id',
                    'failure_code',
                    'failure_message',
                ])
                ->withQueryString(),
            'delivery_summary' => $summaryService->summary($campaign),
            'daily_quota' => $recipientQuota->snapshot(),
            'variable_mappings' => $campaign->variableMappings()
                ->get(['variable', 'source_type', 'source_column_key', 'fixed_value']),
            'previews' => $previewService->previews($campaign),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(WhatsappTemplate $template): array
    {
        return [
            'id' => $template->id,
            'meta_template_id' => $template->meta_template_id,
            'name' => $template->name,
            'language_code' => $template->language_code,
            'category' => $template->category,
            'body_text' => $template->body_text,
            'body_variables' => $template->body_variables,
            'components' => $template->components,
        ];
    }
}
