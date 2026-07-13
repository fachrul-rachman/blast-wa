<?php

namespace App\Console\Commands;

use App\Services\Campaigns\DueCampaignDispatcher;
use Illuminate\Console\Command;

class DispatchDueCampaignsCommand extends Command
{
    protected $signature = 'campaigns:dispatch-due';

    protected $description = 'Dispatch due scheduled campaigns.';

    public function handle(DueCampaignDispatcher $dispatcher): int
    {
        $started = $dispatcher->dispatchDue();

        $this->info("Started {$started} due campaign(s).");

        return self::SUCCESS;
    }
}
