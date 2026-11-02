<?php

namespace MainaSuperintelligence\Whatsapp\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use MainaSuperintelligence\Whatsapp\Events\WhatsappContactOptedOut;
use MainaSuperintelligence\Whatsapp\Models\WhatsappContact;
use MainaSuperintelligence\Whatsapp\Tables\ContactTable;
use Carbon\Carbon;

class ContactController extends BaseController
{
    public function index(ContactTable $table)
    {
        $this->pageTitle(trans('plugins/whatsapp::whatsapp.contacts.title'));

        return $table->renderTable();
    }

    public function toggleOptOut(WhatsappContact $contact): BaseHttpResponse
    {
        $optedOut = ! $contact->opted_out;

        $contact->forceFill([
            'opted_out' => $optedOut,
            'opted_out_at' => $optedOut ? Carbon::now() : null,
        ])->save();

        WhatsappContactOptedOut::dispatch($contact, $optedOut);

        return $this
            ->httpResponse()
            ->setMessage(trans($optedOut
                ? 'plugins/whatsapp::whatsapp.contacts.now_opted_out'
                : 'plugins/whatsapp::whatsapp.contacts.now_opted_in'));
    }
}
