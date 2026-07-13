<?php

namespace App\Models;

use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property int $whatsapp_template_id
 * @property array<string, mixed> $template_snapshot
 * @property string $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $consent_confirmed_at
 * @property array<string, mixed>|null $import_summary
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappTemplate $whatsappTemplate
 * @property-read Collection<int, CampaignRecipient> $recipients
 * @property-read Collection<int, CampaignVariableMapping> $variableMappings
 */
#[Fillable([
    'title',
    'whatsapp_template_id',
    'template_snapshot',
    'status',
    'scheduled_at',
    'started_at',
    'completed_at',
    'cancelled_at',
    'consent_confirmed_at',
    'import_summary',
    'last_error',
])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    /**
     * @return BelongsTo<WhatsappTemplate, $this>
     */
    public function whatsappTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class);
    }

    /**
     * @return HasMany<CampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * @return HasMany<CampaignVariableMapping, $this>
     */
    public function variableMappings(): HasMany
    {
        return $this->hasMany(CampaignVariableMapping::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_snapshot' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'consent_confirmed_at' => 'datetime',
            'import_summary' => 'array',
        ];
    }
}
