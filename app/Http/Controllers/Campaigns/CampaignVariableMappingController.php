<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Campaigns\CampaignEditGuard;
use App\Services\Campaigns\VariableMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CampaignVariableMappingController extends Controller
{
    public function update(Request $request, Campaign $campaign, VariableMappingService $service, CampaignEditGuard $guard): RedirectResponse
    {
        $validated = $request->validate([
            'mappings' => ['required', 'array'],
            'mappings.*.variable' => ['required', 'string', 'max:255'],
            'mappings.*.source_type' => ['required', 'string', 'in:column,fixed'],
            'mappings.*.source_column_key' => ['nullable', 'string', 'max:255'],
            'mappings.*.fixed_value' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $guard->ensureDraft($campaign);
            $service->save($campaign, $validated['mappings']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['mappings' => $exception->getMessage()]);
        }

        return redirect()->route('campaigns.show', $campaign);
    }
}
