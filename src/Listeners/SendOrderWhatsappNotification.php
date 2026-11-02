<?php

namespace MainaSuperintelligence\Whatsapp\Listeners;

use Botble\Base\Facades\BaseHelper;
use Botble\Ecommerce\Events\OrderCompletedEvent;
use Botble\Ecommerce\Events\OrderConfirmedEvent;
use Botble\Ecommerce\Events\OrderPaymentConfirmedEvent;
use Botble\Ecommerce\Models\Order;
use MainaSuperintelligence\Whatsapp\Services\WhatsappService;
use MainaSuperintelligence\Whatsapp\Supports\TemplateRegistry;
use MainaSuperintelligence\Whatsapp\Supports\WhatsappSettings;
use Throwable;

/**
 * Built-in ecommerce notifications. Only sent when the event is enabled and mapped on the
 * WhatsApp > Templates page. These never fall back to SMS because the sms plugin sends its
 * own order SMS for the same events.
 */
class SendOrderWhatsappNotification
{
    public function __construct(protected WhatsappService $service)
    {
    }

    public function handle(OrderConfirmedEvent|OrderPaymentConfirmedEvent|OrderCompletedEvent $event): void
    {
        $key = match (true) {
            $event instanceof OrderConfirmedEvent => 'order_confirmed',
            $event instanceof OrderPaymentConfirmedEvent => 'order_payment_received',
            default => 'order_completed',
        };

        try {
            $this->send($event->order, $key);
        } catch (Throwable $exception) {
            // Never break checkout because of a notification.
            BaseHelper::logError($exception);
        }
    }

    public function send(Order $order, string $key): void
    {
        if (! WhatsappSettings::enabled()) {
            return;
        }

        $definition = TemplateRegistry::get($key);

        if (! $definition || ! WhatsappSettings::templateMapping($key, $definition)['enabled']) {
            return;
        }

        $phone = $order->address->phone ?: $order->user->phone;

        if (! $phone || ! $this->service->normalisePhone((string) $phone)) {
            return;
        }

        // The same event can fire more than once (e.g. re-confirming); send each notification once per order.
        $alreadySent = \MainaSuperintelligence\Whatsapp\Models\WhatsappMessage::query()
            ->where('template_key', $key)
            ->where('related_type', $order->getMorphClass())
            ->where('related_id', $order->getKey())
            ->whereIn('status', ['queued', 'sent', 'delivered', 'read'])
            ->exists();

        if ($alreadySent) {
            return;
        }

        $name = $order->address->name ?: $order->user->name ?: 'there';
        $amount = $key === 'order_payment_received' && $order->payment && $order->payment->amount
            ? (float) $order->payment->amount
            : (float) $order->amount;

        $this->service->sendTemplate((string) $phone, $key, [
            'customer_name' => $name,
            'order_code' => (string) $order->code,
            'order_total' => format_price($order->amount),
            'amount' => format_price($amount),
            'order_id' => (string) $order->getKey(),
        ], [
            'related' => $order,
            'customer_id' => $order->user_id ?: null,
            'name' => $name,
            'fallback_sms' => false,
        ]);
    }
}
