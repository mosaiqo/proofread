<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Mosaiqo\Proofread\Events\EvalRunRegressed;
use Mosaiqo\Proofread\Webhooks\RegressionWebhookNotifier;

/**
 * Queued listener that fans regression events out to the configured webhook
 * endpoints. Runs on the queue so HTTP latency never blocks persist() callers
 * (suites, Artisan commands, jobs). Falls back to sync when the consumer
 * application has no queue worker because the default queue connection in
 * Laravel is "sync" unless configured otherwise.
 */
final class NotifyWebhookOnRegression implements ShouldQueue
{
    public function __construct(
        private readonly RegressionWebhookNotifier $notifier,
    ) {}

    public function handle(EvalRunRegressed $event): void
    {
        $this->notifier->notify($event);
    }
}
