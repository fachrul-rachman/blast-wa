<?php

namespace App\Http\Controllers\Templates;

use App\Http\Controllers\Controller;
use App\Models\WhatsappTemplate;
use App\Services\Meta\MetaApiException;
use App\Services\Templates\TemplateSyncService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('templates/index', [
            'templates' => WhatsappTemplate::query()
                ->latest('synced_at')
                ->latest()
                ->get([
                    'id',
                    'name',
                    'language_code',
                    'category',
                    'status',
                    'body_variables',
                    'is_supported',
                    'is_available',
                    'synced_at',
                ]),
        ]);
    }

    public function show(WhatsappTemplate $template): Response
    {
        return Inertia::render('templates/show', [
            'template' => $template->only([
                'id',
                'name',
                'language_code',
                'category',
                'status',
                'body_text',
                'body_variables',
                'components',
                'is_supported',
                'is_available',
                'synced_at',
            ]),
        ]);
    }

    public function sync(TemplateSyncService $service): RedirectResponse
    {
        try {
            $count = $service->sync();
        } catch (MetaApiException) {
            return back()->withErrors([
                'sync' => 'Template sync failed. Check Meta WhatsApp configuration and try again.',
            ]);
        }

        return redirect()
            ->route('templates.index')
            ->with('status', "Synced {$count} templates from Meta.");
    }
}
