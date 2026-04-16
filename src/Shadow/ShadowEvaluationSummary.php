<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Shadow;

final readonly class ShadowEvaluationSummary
{
    public function __construct(
        public int $capturesProcessed,
        public int $capturesSkipped,
        public int $evalsCreated,
        public int $passed,
        public int $failed,
        public float $totalJudgeCostUsd,
        public float $durationMs,
    ) {}

    public function passRate(): float
    {
        return $this->evalsCreated === 0 ? 1.0 : $this->passed / $this->evalsCreated;
    }
}
