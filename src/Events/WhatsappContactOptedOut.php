<?php

namespace Maina888\Whatsapp\Events;

use Botble\Base\Events\Event;
use Maina888\Whatsapp\Models\WhatsappContact;
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
