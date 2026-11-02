<?php

namespace MainaSuperintelligence\Whatsapp\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use MainaSuperintelligence\Whatsapp\Enums\MessageStatusEnum;
use MainaSuperintelligence\Whatsapp\Models\WhatsappContact;
use MainaSuperintelligence\Whatsapp\Models\WhatsappMessage;
use MainaSuperintelligence\Whatsapp\Services\WhatsappService;
use Illuminate\Http\Request;

class InboxController extends BaseController
{
    public function index(Request $request)
    {
        $this->pageTitle(trans('plugins/whatsapp::whatsapp.inbox.title'));

        $search = trim((string) $request->query('q', ''));

        $conversations = WhatsappContact::query()
            ->whereNotNull('last_inbound_at')
            ->when($search !== '', function ($query) use ($search): void {
                $digits = preg_replace('/\D+/', '', $search);

                $query->where(function ($query) use ($search, $digits): void {
                    $query->where('name', 'LIKE', '%' . $search . '%');

                    if ($digits !== '') {
                        $query->orWhere('phone', 'LIKE', '%' . $digits . '%');
                    }
                });
            })
            ->orderByDesc('last_inbound_at')
            ->paginate(30)
            ->withQueryString();

        $contact = null;
        $thread = collect();

        if ($contactId = $request->integer('contact')) {
            $contact = WhatsappContact::query()->find($contactId);
        } elseif ($conversations->isNotEmpty()) {
            $contact = $conversations->first();
        }

        if ($contact) {
            $thread = WhatsappMessage::query()
                ->where(function ($query) use ($contact): void {
                    $query->where('contact_id', $contact->getKey())->orWhere('phone', $contact->phone);
                })
                ->latest('id')
                ->limit(100)
                ->get()
                ->reverse()
                ->values();
        }

        return view('plugins/whatsapp::inbox.index', compact('conversations', 'contact', 'thread', 'search'));
    }

    public function reply(WhatsappContact $contact, Request $request, WhatsappService $service): BaseHttpResponse
    {
        $request->validate(['message' => ['required', 'string', 'max:4096']]);

        $response = $this->httpResponse()
            ->setPreviousRoute('whatsapp.inbox.index')
            ->setPreviousUrl(route('whatsapp.inbox.index', ['contact' => $contact->getKey()]))
            ->setNextUrl(route('whatsapp.inbox.index', ['contact' => $contact->getKey()]));

        if (! $contact->isWithinWindow()) {
            return $response
                ->setError()
                ->setMessage(trans('plugins/whatsapp::whatsapp.inbox.window_closed'));
        }

        $message = $service->sendText($contact->phone, (string) $request->input('message'), [
            'force' => true,
            'ignore_opt_out' => true,
            'fallback_sms' => false,
            'sent_by' => auth()->id(),
        ]);

        if ($message->statusValue() === MessageStatusEnum::FAILED) {
            return $response
                ->setError()
                ->withInput()
                ->setMessage(trans('plugins/whatsapp::whatsapp.inbox.reply_failed', ['error' => $message->error]));
        }

        return $response->setMessage(trans('plugins/whatsapp::whatsapp.inbox.reply_sent'));
    }
}
