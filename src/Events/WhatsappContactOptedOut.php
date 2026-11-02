<?php

namespace MainaSuperintelligence\Whatsapp\Events;

use Botble\Base\Events\Event;
use MainaSuperintelligence\Whatsapp\Models\WhatsappContact;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsappContactOptedOut extends Event
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public WhatsappContact $contact, public bool $optedOut = true)
    {
    }
}
