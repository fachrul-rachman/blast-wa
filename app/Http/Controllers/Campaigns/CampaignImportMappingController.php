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

class CampaignImportMappingController extends Controller
{
    public function update(Request $request, Campaign $campaign, CampaignImportService $service, CampaignEditGuard $guard): RedirectResponse
    {
        $validated = $request->validate([
            'phone_column_key' => ['required', 'string', 'max:255'],
            'name_column_key' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $guard->ensureDraft($campaign);

            $service->remap(
                $campaign,
                $validated['phone_column_key'],
                $validated['name_column_key'] ?? null,
            );
        } catch (ImportException $exception) {
            return back()->withErrors([
                'phone_column_key' => $exception->getMessage(),
            ]);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors([
                'campaign' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('campaigns.show', $campaign);
    }
}
