<?php

namespace App\Models;

use Database\Factories\WhatsappTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $meta_template_id
 * @property string $name
 * @property string $language_code
 * @property string|null $category
 * @property string $status
 * @property string|null $body_text
 * @property array<int, string> $body_variables
 * @property array<int, array<string, mixed>> $components
 * @property bool $is_supported
 * @property bool $is_available
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'meta_template_id',
    'name',
    'language_code',
    'category',
    'status',
    'body_text',
    'body_variables',
    'components',
    'is_supported',
    'is_available',
    'synced_at',
])]
class WhatsappTemplate extends Model
{
    /** @use HasFactory<WhatsappTemplateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body_variables' => 'array',
            'components' => 'array',
            'is_supported' => 'boolean',
            'is_available' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
