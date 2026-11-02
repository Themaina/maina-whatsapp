<?php

namespace Maina888\Whatsapp\Models;

use Botble\Base\Models\BaseModel;
use Maina888\Whatsapp\Enums\MessageDirectionEnum;
use Maina888\Whatsapp\Enums\MessageStatusEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property MessageDirectionEnum $direction
 * @property int|null $contact_id
 * @property string $phone
 * @property string|null $to
 * @property string|null $from
 * @property string $type
 * @property string|null $template_key
 * @property string|null $template_name
 * @property string|null $language
 * @property string|null $body
 * @property array|null $payload
 * @property MessageStatusEnum $status
 * @property string|null $provider_message_id
 * @property string|null $error_code
 * @property string|null $error
 * @property int $attempts
 * @property array|null $context
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $failed_at
 */
class WhatsappMessage extends BaseModel
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'direction',
        'contact_id',
        'phone',
        'to',
        'from',
        'type',
        'template_key',
        'template_name',
        'language',
        'body',
        'payload',
        'status',
        'provider_message_id',
        'error_code',
        'error',
        'attempts',
        'context',
        'related_type',
        'related_id',
        'resent_from_id',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    protected $casts = [
        'direction' => MessageDirectionEnum::class,
        'status' => MessageStatusEnum::class,
        'payload' => 'array',
        'context' => 'array',
        'attempts' => 'int',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class, 'contact_id')->withDefault();
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function isOutbound(): bool
    {
        return $this->direction?->getValue() === MessageDirectionEnum::OUT;
    }

    public function statusValue(): ?string
    {
        return $this->status?->getValue();
    }

    public function canBeResent(): bool
    {
        return $this->isOutbound() && $this->statusValue() === MessageStatusEnum::FAILED;
    }
}
