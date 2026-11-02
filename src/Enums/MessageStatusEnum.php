<?php

namespace MainaSuperintelligence\Whatsapp\Enums;

use Botble\Base\Facades\BaseHelper;
use Botble\Base\Supports\Enum;
use Illuminate\Support\HtmlString;

/**
 * @method static MessageStatusEnum QUEUED()
 * @method static MessageStatusEnum SENT()
 * @method static MessageStatusEnum DELIVERED()
 * @method static MessageStatusEnum READ()
 * @method static MessageStatusEnum FAILED()
 * @method static MessageStatusEnum RECEIVED()
 */
class MessageStatusEnum extends Enum
{
    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const READ = 'read';

    public const FAILED = 'failed';

    public const RECEIVED = 'received';

    public static $langPath = 'plugins/whatsapp::whatsapp.statuses';

    /**
     * Progress rank, so webhook updates arriving out of order never move a message backwards.
     */
    public static function rank(?string $status): int
    {
        return match ($status) {
            self::QUEUED => 0,
            self::SENT => 1,
            self::DELIVERED => 2,
            self::READ => 3,
            self::FAILED => 4,
            self::RECEIVED => 5,
            default => -1,
        };
    }

    public function toHtml(): HtmlString|string
    {
        $color = match ($this->value) {
            self::QUEUED => 'secondary',
            self::SENT => 'info',
            self::DELIVERED => 'primary',
            self::READ => 'success',
            self::FAILED => 'danger',
            self::RECEIVED => 'warning',
            default => 'secondary',
        };

        return BaseHelper::renderBadge($this->label(), $color);
    }
}
