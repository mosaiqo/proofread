<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Judge;

final readonly class JudgeVerdict
{
    public function __construct(
        public bool $passed,
        public float $score,
        public string $reason,
    ) {}
}
