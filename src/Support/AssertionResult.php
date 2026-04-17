<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Support;

use LogicException;

/**
 * Result of running a single Assertion.
 *
 * @internal This class is sealed — only {@see JudgeResult} is an
 *           allowed subclass. External subclassing throws a
 *           {@see LogicException} at construction time. This is
 *           enforced because internal invariants assume a closed
 *           type hierarchy.
 */
class AssertionResult
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  ?float  $score  Arbitrary numeric score. The consumer interprets
     *                         the meaning based on the assertion. Common
     *                         conventions:
     *                         - [0, 1] for probabilities, pass rates, confidence.
     *                         - [-1, 1] for cosine similarity.
     *                         - Other ranges are allowed per assertion (magnitudes,
     *                         z-scores, etc.). `null` means "no score produced".
     */
    protected function __construct(
        public readonly bool $passed,
        public readonly string $reason,
        public readonly ?float $score = null,
        public readonly array $metadata = [],
    ) {
        $this->assertSealed();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function pass(string $reason = '', ?float $score = null, array $metadata = []): self
    {
        return new self(true, $reason, $score, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function fail(string $reason, ?float $score = null, array $metadata = []): self
    {
        return new self(false, $reason, $score, $metadata);
    }

    private function assertSealed(): void
    {
        $allowed = [self::class, JudgeResult::class];

        if (! in_array(static::class, $allowed, true)) {
            throw new LogicException(sprintf(
                '%s is sealed and cannot be extended by [%s]. Use composition or open an issue to discuss extending the allowed list.',
                self::class,
                static::class,
            ));
        }
    }
}
