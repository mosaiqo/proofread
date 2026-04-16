<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Diff;

final readonly class CaseDelta
{
    /**
     * @param  list<string>  $newFailures
     * @param  list<string>  $fixedFailures
     */
    public function __construct(
        public int $caseIndex,
        public ?string $caseName,
        public bool $basePassed,
        public bool $headPassed,
        public string $status,
        public ?float $baseCostUsd,
        public ?float $headCostUsd,
        public ?float $baseDurationMs,
        public ?float $headDurationMs,
        public array $newFailures,
        public array $fixedFailures,
    ) {}
}
