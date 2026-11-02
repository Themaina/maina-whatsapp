<?php

namespace MainaSuperintelligence\Whatsapp\Models;

use Botble\Base\Models\BaseModel;

/**
 * A message template as last synced from Meta (GET /{waba_id}/message_templates).
 *
 * @property string $name
 * @property string $language
 * @property string|null $status
 * @property string|null $category
 * @property string|null $body
 * @property int $body_variables
 * @property bool $has_url_button
 * @property array|null $components
 */
class WhatsappTemplate extends BaseModel
{
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'meta_id',
        'name',
        'language',
        'status',
        'category',
        'body',
        'body_variables',
        'has_url_button',
        'components',
        'synced_at',
    ];

    protected $casts = [
        'components' => 'array',
        'body_variables' => 'int',
        'has_url_button' => 'bool',
        'synced_at' => 'datetime',
    ];

    public function isApproved(): bool
    {
        return strtoupper((string) $this->status) === 'APPROVED';
    }
}
