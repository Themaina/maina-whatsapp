<?php

namespace MainaSuperintelligence\Whatsapp\Supports;

/**
 * Templates that ship with the plugin: the ecommerce order events (registered when ecommerce is
 * active). Other plugins add their own through the `whatsapp_templates` filter at a later priority;
 * theirs replace these by key.
 */
class BuiltInTemplates
{
    public static function register(array $templates): array
    {
        if (is_plugin_active('ecommerce')) {
            $templates = array_merge($templates, static::ecommerce());
        }

        return $templates;
    }

    public static function ecommerce(): array
    {
        return [
            'order_confirmed' => [
                'label' => 'Order confirmed',
                'group' => 'Ecommerce',
                'template_name' => 'order_confirmed',
                'variables' => ['customer_name', 'order_code', 'order_total'],
                'button_variable' => 'order_id',
                'button_url' => 'customer/orders/view/{{1}}',
                'preview' => 'Hi {{customer_name}}, your order {{order_code}} for {{order_total}} is confirmed. We will let you know as soon as it is on its way.',
                'sample' => ['customer_name' => 'Demo Customer', 'order_code' => '#10000123', 'order_total' => '$45.00', 'order_id' => '123'],
            ],
            'order_payment_received' => [
                'label' => 'Order payment received',
                'group' => 'Ecommerce',
                'template_name' => 'order_payment_received',
                'variables' => ['customer_name', 'amount', 'order_code'],
                'button_variable' => 'order_id',
                'button_url' => 'customer/orders/view/{{1}}',
                'preview' => 'Hi {{customer_name}}, we have received your payment of {{amount}} for order {{order_code}}. Thank you for shopping with us.',
                'sample' => ['customer_name' => 'Demo Customer', 'amount' => '$45.00', 'order_code' => '#10000123', 'order_id' => '123'],
            ],
            'order_completed' => [
                'label' => 'Order completed',
                'group' => 'Ecommerce',
                'template_name' => 'order_completed',
                'variables' => ['customer_name', 'order_code'],
                'button_variable' => 'order_id',
                'button_url' => 'customer/orders/view/{{1}}',
                'preview' => 'Hi {{customer_name}}, your order {{order_code}} is complete. Thank you for shopping with us!',
                'sample' => ['customer_name' => 'Demo Customer', 'order_code' => '#10000123', 'order_id' => '123'],
            ],
        ];
    }
}
