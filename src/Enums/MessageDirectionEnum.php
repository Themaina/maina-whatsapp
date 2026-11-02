<?php

namespace Maina888\Whatsapp\Enums;

use Botble\Base\Facades\BaseHelper;
use Botble\Base\Supports\Enum;
use Illuminate\Support\HtmlString;

/**
 * @method static MessageDirectionEnum IN()
 * @method static MessageDirectionEnum OUT()
 */
class MessageDirectionEnum extends Enum
{
    public const IN = 'in';

    public const OUT = 'out';

    public static $langPath = 'plugins/whatsapp::whatsapp.directions';

    public function toHtml(): HtmlString|string
    {
        return BaseHelper::renderBadge($this->label(), $this->value === self::IN ? 'warning' : 'info');
    }
}
