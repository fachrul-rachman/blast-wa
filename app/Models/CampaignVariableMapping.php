<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property string $variable
 * @property string $source_type
 * @property string|null $source_column_key
 * @property string|null $fixed_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'campaign_id',
    'variable',
    'source_type',
    'source_column_key',
    'fixed_value',
])]
class CampaignVariableMapping extends Model
{
    public const SOURCE_COLUMN = 'column';

    public const SOURCE_FIXED = 'fixed';

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
