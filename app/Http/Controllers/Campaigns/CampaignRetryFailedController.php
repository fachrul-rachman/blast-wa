<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Campaigns\CampaignRetryFailedService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class CampaignRetryFailedController extends Controller
{
    public function store(Campaign $campaign, CampaignRetryFailedService $retryService): RedirectResponse
    {
        try {
            $retryService->retry($campaign);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['retry' => $exception->getMessage()]);
        }

        return redirect()->route('campaigns.show', $campaign);
    }
}
