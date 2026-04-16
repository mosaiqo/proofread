<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Listeners;

use Mosaiqo\Proofread\Diff\EvalRunDiff;
use Mosaiqo\Proofread\Events\EvalRunPersisted;
use Mosaiqo\Proofread\Events\EvalRunRegressed;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * Reacts to EvalRunPersisted, locates the most recent prior run of the same
 * dataset, and dispatches EvalRunRegressed when the diff surfaces any
 * regression. Runs synchronously to keep the decision close to the persist
 * transaction; dispatching the actual webhook call is the queued concern of
 * NotifyWebhookOnRegression.
 */
final class CheckForRegressionListener
{
    public function __construct(
        private readonly EvalRunDiff $diff,
    ) {}

    public function handle(EvalRunPersisted $event): void
    {
        $current = $event->run;

        /** @var EvalRun|null $previous */
        $previous = EvalRun::query()
            ->where('dataset_name', $current->dataset_name)
            ->where('id', '!=', $current->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($previous === null) {
            return;
        }

        $delta = $this->diff->compute($previous, $current);

        if (! $delta->hasRegressions()) {
            return;
        }

        event(new EvalRunRegressed($previous, $current, $delta));
    }
}
