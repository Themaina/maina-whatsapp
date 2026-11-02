<?php

namespace MainaSuperintelligence\Whatsapp\Tests\Feature;

use MainaSuperintelligence\Whatsapp\Services\WhatsappService;
use MainaSuperintelligence\Whatsapp\Supports\TemplateRegistry;
use MainaSuperintelligence\Whatsapp\Tests\WhatsappTestCase;
use ReflectionMethod;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Contract for other plugins: the `whatsapp_templates` registry (including alias keys), the
 * built-in order templates and the public WhatsappService signatures.
 */
class TemplateContractTest extends WhatsappTestCase
{
    public function test_ecommerce_templates_are_registered(): void
    {
        if (! is_plugin_active('ecommerce')) {
            $this->markTestSkipped('ecommerce is not active.');
        }

        $all = TemplateRegistry::all();

        foreach (['order_confirmed', 'order_payment_received', 'order_completed'] as $key) {
            $this->assertArrayHasKey($key, $all);
            $this->assertSame('order_id', $all[$key]['button_variable']);
        }
    }

    public function test_other_plugins_can_register_templates(): void
    {
        add_filter('whatsapp_templates', function (array $templates): array {
            $templates['it_plugintests_shipped'] = [
                'label' => 'Order shipped',
                'group' => 'Shipping',
                'template_name' => 'order_shipped',
                'variables' => ['customer_name', 'order_code', 'tracking_id'],
                'preview' => 'Hi {{customer_name}}, order {{order_code}} is on its way (tracking {{tracking_id}}).',
            ];

            return $templates;
        }, 30);

        $definition = TemplateRegistry::get('it_plugintests_shipped');

        $this->assertNotNull($definition);

        $vars = ['customer_name' => 'Demo Customer', 'order_code' => '#10000123', 'tracking_id' => 'TRK123'];
        $parameters = TemplateRegistry::parameters($definition, $vars);
        $text = TemplateRegistry::render($definition, $vars, $parameters['body']);

        $this->assertSame(['Demo Customer', '#10000123', 'TRK123'], $parameters['body']);
        $this->assertStringNotContainsString('{', $text);
        $this->assertStringContainsString('TRK123', $text);
    }

    public function test_alias_keys_and_list_registration(): void
    {
        add_filter('whatsapp_templates', function (array $templates): array {
            $templates['it_plugintests_alias'] = [
                'template' => 'meta_name',
                'url_variable' => 'link',
                'default' => 'Hi {customer_name}',
                'variables' => ['customer_name', '', null, 'plan_name'],
            ];
            $templates[] = ['key' => 'it_plugintests_list', 'variables' => ['a']];
            $templates['it_plugintests_broken'] = 'not an array';

            return $templates;
        }, 999);

        $alias = TemplateRegistry::get('it_plugintests_alias');

        $this->assertSame('meta_name', $alias['template_name']);
        $this->assertSame('link', $alias['button_variable']);
        $this->assertSame(['customer_name', 'plan_name'], $alias['variables']);
        $this->assertSame('Hi Jane', TemplateRegistry::render($alias, ['customer_name' => 'Jane'], ['Jane', '-']));
        $this->assertNotNull(TemplateRegistry::get('it_plugintests_list'));
        $this->assertNull(TemplateRegistry::get('it_plugintests_broken'));
    }

    public function test_public_service_signatures(): void
    {
        $sendTemplate = array_map(fn ($parameter) => $parameter->getName(), (new ReflectionMethod(WhatsappService::class, 'sendTemplate'))->getParameters());
        $sendText = array_map(fn ($parameter) => $parameter->getName(), (new ReflectionMethod(WhatsappService::class, 'sendText'))->getParameters());

        $this->assertSame(['to', 'key', 'vars', 'context'], $sendTemplate);
        $this->assertSame(['to', 'text', 'context'], $sendText);
        $this->assertSame('254712345678', app(WhatsappService::class)->normalisePhone('0712 345 678'));
        $this->assertNull(app(WhatsappService::class)->normalisePhone('12'));
    }
}
