<?php

namespace App\Services\Campaigns;

use RuntimeException;

class DailyRecipientQuotaExceeded extends RuntimeException
{
    public function __construct(public readonly int $delaySeconds)
    {
        parent::__construct('Daily WhatsApp recipient quota is exhausted.');
    }
}
