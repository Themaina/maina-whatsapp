<?php

namespace MainaSuperintelligence\Whatsapp\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use MainaSuperintelligence\Whatsapp\Services\WhatsappService;

class WidgetController extends BaseController
{
    public function stats(WhatsappService $service): BaseHttpResponse
    {
        $stats = $service->todayStats();

        return $this
            ->httpResponse()
            ->setData(view('plugins/whatsapp::widgets.stats', compact('stats'))->render());
    }
}
