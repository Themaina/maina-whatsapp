<?php

namespace Maina888\Whatsapp\Providers;

use Botble\Ecommerce\Events\OrderCompletedEvent;
use Botble\Ecommerce\Events\OrderConfirmedEvent;
use Botble\Ecommerce\Events\OrderPaymentConfirmedEvent;
use Maina888\Whatsapp\Listeners\SendOrderWhatsappNotification;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! is_plugin_active('ecommerce') || ! class_exists(OrderConfirmedEvent::class)) {
            return;
        }

        $events = $this->app['events'];

        $events->listen(OrderConfirmedEvent::class, [SendOrderWhatsappNotification::class, 'handle']);
        $events->listen(OrderPaymentConfirmedEvent::class, [SendOrderWhatsappNotification::class, 'handle']);
        $events->listen(OrderCompletedEvent::class, [SendOrderWhatsappNotification::class, 'handle']);
    }
}
