<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Campaigns\CampaignEditGuard;
use App\Services\Campaigns\DuplicateResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CampaignDuplicateController extends Controller
{
    public function update(Request $request, Campaign $campaign, DuplicateResolutionService $service, CampaignEditGuard $guard): RedirectResponse
    {
        $validated = $request->validate([
            'duplicate_group_key' => ['required', 'string', 'max:255'],
            'winner_id' => ['required', 'integer'],
        ]);

        try {
            $guard->ensureDraft($campaign);
            $service->chooseWinner($campaign, $validated['duplicate_group_key'], (int) $validated['winner_id']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['winner_id' => $exception->getMessage()]);
        }

        return redirect()->route('campaigns.show', $campaign);
    }
}
