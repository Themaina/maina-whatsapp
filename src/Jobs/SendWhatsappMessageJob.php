<?php

namespace MainaSuperintelligence\Whatsapp\Jobs;

use MainaSuperintelligence\Whatsapp\Enums\MessageStatusEnum;
use MainaSuperintelligence\Whatsapp\Models\WhatsappMessage;
use MainaSuperintelligence\Whatsapp\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Throwable;

/**
 * Delivers one queued WhatsApp message. Transient failures (network, 5xx, rate limits) are
 * released back to the queue with backoff; permanent failures mark the message failed and
 * trigger the SMS fallback.
 */
class SendWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries;

    public int $timeout = 60;

    public function __construct(public int $messageId)
    {
        $this->tries = max(1, (int) config('plugins.whatsapp.general.tries', 5));

        if ($queue = config('plugins.whatsapp.general.queue')) {
            $this->onQueue($queue);
        }
    }

    public function handle(WhatsappService $service): void
    {
        $message = WhatsappMessage::query()->find($this->messageId);

        if (! $message || $message->statusValue() !== MessageStatusEnum::QUEUED) {
            return;
        }

        $outcome = $service->deliver($message, $this->isFinalAttempt());

        if ($outcome === WhatsappService::OUTCOME_RETRY) {
            $this->release($this->retryDelay());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $message = WhatsappMessage::query()->find($this->messageId);

        if ($message && $message->statusValue() === MessageStatusEnum::QUEUED) {
            app(WhatsappService::class)->markFailed(
                $message,
                'job_failed',
                $exception ? $exception->getMessage() : 'Delivery job failed.'
            );
        }
    }

    protected function isFinalAttempt(): bool
    {
        return ! $this->job
            || $this->job instanceof SyncJob
            || $this->attempts() >= $this->tries;
    }

    protected function retryDelay(): int
    {
        $backoff = (array) config('plugins.whatsapp.general.backoff', [30, 120, 600, 1800]);

        if (! $backoff) {
            return 60;
        }

        $index = max(0, min(count($backoff) - 1, $this->attempts() - 1));

        return (int) $backoff[$index];
    }
}
