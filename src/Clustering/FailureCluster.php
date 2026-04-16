<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Clustering;

/**
 * A single cluster of semantically similar failure signals.
 *
 * @internal Produced by FailureClusterer; treat as read-only.
 */
final readonly class FailureCluster
{
    /**
     * @param  list<int>  $memberIndexes  Indexes into the original signals array.
     * @param  list<string>  $memberSignals  Full signal strings for each member, in original order.
     */
    public function __construct(
        public string $representative,
        public array $memberIndexes,
        public array $memberSignals,
    ) {}

    public function size(): int
    {
        return count($this->memberIndexes);
    }
}
