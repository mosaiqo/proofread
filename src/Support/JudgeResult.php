<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Support;

use InvalidArgumentException;

final class JudgeResult extends AssertionResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        bool $passed,
        string $reason,
        ?float $score,
        array $metadata,
        public readonly string $judgeModel,
        public readonly int $retryCount,
    ) {
        if ($judgeModel === '') {
            throw new InvalidArgumentException('Judge model must not be empty.');
        }

        if ($retryCount < 0) {
            throw new InvalidArgumentException(
                "Retry count must be non-negative, got {$retryCount}."
            );
        }

        parent::__construct($passed, $reason, $score, $metadata);
    }

    /**
     * Build a passing judge result. Note: parameter order follows
     * AssertionResult::pass() for LSP compliance, with judge-specific
     * fields appended after metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function pass(
        string $reason = '',
        ?float $score = null,
        array $metadata = [],
        string $judgeModel = '',
        int $retryCount = 0,
    ): self {
        if ($score === null) {
            throw new InvalidArgumentException(
                'JudgeResult::pass requires a numeric score (judges always produce a score when passing).'
            );
        }

        return new self(true, $reason, $score, $metadata, $judgeModel, $retryCount);
    }

    /**
     * Build a failing judge result. Score may be null (e.g., when the judge
     * itself errored out and never produced a score).
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function fail(
        string $reason,
        ?float $score = null,
        array $metadata = [],
        string $judgeModel = '',
        int $retryCount = 0,
    ): self {
        return new self(false, $reason, $score, $metadata, $judgeModel, $retryCount);
    }
}
