<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Campaigns\CampaignScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CampaignScheduleController extends Controller
{
    public function store(Request $request, Campaign $campaign, CampaignScheduleService $scheduleService): RedirectResponse
    {
        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'consent_confirmed' => ['accepted'],
        ]);

        return $this->schedule($campaign, $scheduleService, (string) $validated['scheduled_at']);
    }

    public function update(Request $request, Campaign $campaign, CampaignScheduleService $scheduleService): RedirectResponse
    {
        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        return $this->schedule($campaign, $scheduleService, (string) $validated['scheduled_at']);
    }

    public function destroy(Campaign $campaign, CampaignScheduleService $scheduleService): RedirectResponse
    {
        try {
            $scheduleService->cancel($campaign);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()]);
        }

        return redirect()->route('campaigns.show', $campaign);
    }

    private function schedule(Campaign $campaign, CampaignScheduleService $scheduleService, string $scheduledAt): RedirectResponse
    {
        try {
            $scheduleService->schedule($campaign, CarbonImmutable::parse($scheduledAt, (string) config('app.timezone')));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()]);
        }

        return redirect()->route('campaigns.show', $campaign);
    }
}
