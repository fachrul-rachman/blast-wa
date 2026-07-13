<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $fingerprint
 * @property string|null $meta_message_id
 * @property string|null $event_status
 * @property Carbon|null $event_timestamp
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'fingerprint',
    'meta_message_id',
    'event_status',
    'event_timestamp',
    'payload',
    'processed_at',
])]
class WebhookEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_timestamp' => 'datetime',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
