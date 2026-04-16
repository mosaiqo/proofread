<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Support;

use InvalidArgumentException;
use Throwable;

final readonly class EvalResult
{
    /**
     * @param  array<string, mixed>  $case
     * @param  list<AssertionResult>  $assertionResults
     */
    private function __construct(
        public array $case,
        public mixed $output,
        public array $assertionResults,
        public float $durationMs,
        public ?Throwable $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $case
     * @param  array<int, mixed>  $assertionResults
     */
    public static function make(
        array $case,
        mixed $output,
        array $assertionResults,
        float $durationMs,
        ?Throwable $error = null,
    ): self {
        if ($durationMs < 0.0) {
            throw new InvalidArgumentException(
                sprintf('Duration must be >= 0, got %F.', $durationMs)
            );
        }

        $normalized = [];
        foreach ($assertionResults as $index => $result) {
            if (! $result instanceof AssertionResult) {
                throw new InvalidArgumentException(
                    sprintf(
                        'assertionResults[%d] must be an AssertionResult, got %s.',
                        $index,
                        get_debug_type($result),
                    )
                );
            }
            $normalized[] = $result;
        }

        return new self($case, $output, $normalized, $durationMs, $error);
    }

    public function passed(): bool
    {
        if ($this->error !== null) {
            return false;
        }

        foreach ($this->assertionResults as $result) {
            if (! $result->passed) {
                return false;
            }
        }

        return true;
    }

    public function failed(): bool
    {
        return ! $this->passed();
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }
}
