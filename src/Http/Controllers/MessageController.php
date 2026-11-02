<?php

namespace Maina888\Whatsapp\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Maina888\Whatsapp\Enums\MessageStatusEnum;
use Maina888\Whatsapp\Models\WhatsappMessage;
use Maina888\Whatsapp\Services\WhatsappService;
use Maina888\Whatsapp\Tables\MessageTable;

class MessageController extends BaseController
{
    public function index(MessageTable $table)
    {
        $this->pageTitle(trans('plugins/whatsapp::whatsapp.messages.title'));

        return $table->renderTable();
    }

    public function resend(WhatsappMessage $message, WhatsappService $service): BaseHttpResponse
    {
        if (! $message->canBeResent()) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/whatsapp::whatsapp.messages.cannot_resend'));
        }

        $copy = $service->resend($message);

        $failed = $copy->statusValue() === MessageStatusEnum::FAILED;

        return $this
            ->httpResponse()
            ->setError($failed)
            ->setMessage($failed
                ? trans('plugins/whatsapp::whatsapp.messages.resend_failed', ['error' => $copy->error])
                : trans('plugins/whatsapp::whatsapp.messages.resent', ['status' => $copy->status?->label()]));
    }
}
