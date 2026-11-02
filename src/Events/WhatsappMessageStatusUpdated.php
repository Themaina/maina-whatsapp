<?php

namespace MainaSuperintelligence\Whatsapp\Events;

use Botble\Base\Events\Event;
use MainaSuperintelligence\Whatsapp\Models\WhatsappMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsappMessageStatusUpdated extends Event
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public WhatsappMessage $message)
    {
    }
}
