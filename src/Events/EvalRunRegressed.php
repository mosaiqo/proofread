<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Events;

use Mosaiqo\Proofread\Diff\EvalRunDelta;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * Fired when a newly persisted run shows one or more regressions against
 * the previous run of the same dataset. Carries both runs and the full
 * delta so listeners can format notifications without recomputing the diff.
 */
final class EvalRunRegressed
{
    public function __construct(
        public readonly EvalRun $baseRun,
        public readonly EvalRun $headRun,
        public readonly EvalRunDelta $delta,
    ) {}
}
