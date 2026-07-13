<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Campaigns\CampaignStartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CampaignSendController extends Controller
{
    public function store(Request $request, Campaign $campaign, CampaignStartService $startService): RedirectResponse
    {
        $request->validate([
            'consent_confirmed' => ['accepted'],
        ]);

        try {
            $startService->startImmediate($campaign);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['send' => $exception->getMessage()]);
        }

        return redirect()->route('campaigns.show', $campaign);
    }
}
