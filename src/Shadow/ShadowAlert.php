<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Shadow;

use DateTimeImmutable;

/**
 * Immutable snapshot describing why a shadow pass-rate alert fired for a given
 * agent class within a rolling evaluation window.
 */
final readonly class ShadowAlert
{
    public function __construct(
        public string $agentClass,
        public float $passRate,
        public float $threshold,
        public int $sampleSize,
        public int $passedCount,
        public int $failedCount,
        public DateTimeImmutable $windowFrom,
        public DateTimeImmutable $windowTo,
    ) {}
}
