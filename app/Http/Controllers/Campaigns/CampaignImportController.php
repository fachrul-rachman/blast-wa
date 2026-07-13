<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Campaigns\CampaignEditGuard;
use App\Services\Imports\CampaignImportService;
use App\Services\Imports\ImportException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CampaignImportController extends Controller
{
    public function store(Request $request, Campaign $campaign, CampaignImportService $service, CampaignEditGuard $guard): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_file' => [
                'required',
                'file',
                'max:5120',
                'extensions:csv,xlsx',
                'mimes:csv,txt,xlsx',
            ],
            'phone_column_key' => ['nullable', 'string', 'max:255'],
            'name_column_key' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $guard->ensureDraft($campaign);

            $service->import(
                $campaign,
                $validated['recipient_file'],
                $validated['phone_column_key'] ?? null,
                $validated['name_column_key'] ?? null,
            );
        } catch (ImportException $exception) {
            return back()->withErrors([
                'recipient_file' => $exception->getMessage(),
            ]);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors([
                'campaign' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('campaigns.show', $campaign);
    }
}
