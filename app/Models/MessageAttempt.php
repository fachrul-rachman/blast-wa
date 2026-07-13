<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_recipient_id
 * @property int $attempt_number
 * @property array<string, mixed>|null $request_payload_redacted
 * @property array<string, mixed>|null $response_payload_redacted
 * @property string|null $meta_message_id
 * @property string $result
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon $attempted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CampaignRecipient $recipient
 */
#[Fillable([
    'campaign_recipient_id',
    'attempt_number',
    'request_payload_redacted',
    'response_payload_redacted',
    'meta_message_id',
    'result',
    'error_code',
    'error_message',
    'attempted_at',
])]
class MessageAttempt extends Model
{
    public const RESULT_ACCEPTED = 'accepted';

    public const RESULT_FAILED = 'failed';

    /**
     * @return BelongsTo<CampaignRecipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class, 'campaign_recipient_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload_redacted' => 'array',
            'response_payload_redacted' => 'array',
            'attempted_at' => 'datetime',
        ];
    }
}
