<?php

namespace MainaSuperintelligence\Whatsapp\Commands;

use MainaSuperintelligence\Whatsapp\Services\WhatsappService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand('whatsapp:sync-templates', 'Fetch the message templates of the WhatsApp Business Account from Meta')]
class SyncTemplatesCommand extends Command
{
    public function handle(WhatsappService $service): int
    {
        try {
            $result = $service->syncTemplates();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Synced %d templates (%d approved).', $result['total'], $result['approved']));

        return self::SUCCESS;
    }
}
