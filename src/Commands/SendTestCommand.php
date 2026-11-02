<?php

namespace MainaSuperintelligence\Whatsapp\Commands;

use MainaSuperintelligence\Whatsapp\Services\WhatsappService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('whatsapp:send-test', 'Send Meta\'s hello_world template (or a mapped template) to a number')]
class SendTestCommand extends Command
{
    protected $signature = 'whatsapp:send-test {phone} {--template=hello_world : Template key} {--language=en_US}';

    public function handle(WhatsappService $service): int
    {
        $key = (string) $this->option('template');
        $context = ['force' => true, 'fallback_sms' => false, 'test' => true];

        if ($key === 'hello_world') {
            $context += ['template_name' => 'hello_world', 'language' => (string) $this->option('language')];
        }

        $message = $service->sendTemplate((string) $this->argument('phone'), $key, [], $context);

        $this->line(sprintf(
            'Message #%d: %s %s %s',
            $message->getKey(),
            $message->statusValue(),
            $message->provider_message_id ?: '',
            $message->error ? '(' . $message->error_code . ': ' . $message->error . ')' : ''
        ));

        return $message->statusValue() === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
