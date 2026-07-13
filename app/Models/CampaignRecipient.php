<?php

namespace App\Models;

use Database\Factories\CampaignRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int $source_row_number
 * @property string|null $name
 * @property string|null $phone_original
 * @property string|null $phone_normalized
 * @property array<string, string|null> $row_data
 * @property string $validation_status
 * @property array<int, string>|null $validation_errors
 * @property string|null $duplicate_group_key
 * @property bool $is_duplicate_winner
 * @property string $delivery_status
 * @property string|null $meta_message_id
 * @property int $attempt_count
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $failed_at
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, MessageAttempt> $messageAttempts
 */
#[Fillable([
    'campaign_id',
    'source_row_number',
    'name',
    'phone_original',
    'phone_normalized',
    'row_data',
    'validation_status',
    'validation_errors',
    'duplicate_group_key',
    'is_duplicate_winner',
    'delivery_status',
    'meta_message_id',
    'attempt_count',
    'last_attempt_at',
    'accepted_at',
    'sent_at',
    'delivered_at',
    'read_at',
    'failed_at',
    'failure_code',
    'failure_message',
])]
class CampaignRecipient extends Model
{
    /** @use HasFactory<CampaignRecipientFactory> */
    use HasFactory;

    public const VALIDATION_VALID = 'valid';

    public const VALIDATION_INVALID = 'invalid';

    public const VALIDATION_DUPLICATE = 'duplicate';

    public const VALIDATION_MISSING_DATA = 'missing_data';

    public const VALIDATION_SKIPPED = 'skipped';

    public const DELIVERY_PENDING = 'pending';

    public const DELIVERY_QUEUED = 'queued';

    public const DELIVERY_ACCEPTED = 'accepted';

    public const DELIVERY_SENT = 'sent';

    public const DELIVERY_DELIVERED = 'delivered';

    public const DELIVERY_READ = 'read';

    public const DELIVERY_FAILED = 'failed';

    public const DELIVERY_SKIPPED = 'skipped';

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return HasMany<MessageAttempt, $this>
     */
    public function messageAttempts(): HasMany
    {
        return $this->hasMany(MessageAttempt::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_data' => 'array',
            'validation_errors' => 'array',
            'is_duplicate_winner' => 'boolean',
            'last_attempt_at' => 'datetime',
            'accepted_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
