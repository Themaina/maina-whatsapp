<?php

namespace Maina888\Whatsapp\Events;

use Botble\Base\Events\Event;
use Maina888\Whatsapp\Models\WhatsappMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsappMessageSent extends Event
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public WhatsappMessage $message)
    {
    }
}
