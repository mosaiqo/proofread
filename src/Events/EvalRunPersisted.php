<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Events;

use Mosaiqo\Proofread\Models\EvalRun;

/**
 * Fired synchronously inside EvalPersister::persist() after the
 * run, dataset, and result rows have been committed to the database.
 *
 * Consumers can react to this event to compute regressions, trigger
 * notifications, update dashboards, or kick off follow-up jobs.
 */
final class EvalRunPersisted
{
    public function __construct(
        public readonly EvalRun $run,
    ) {}
}
