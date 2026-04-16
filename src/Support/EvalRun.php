<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Support;

use InvalidArgumentException;

final readonly class EvalRun
{
    /**
     * @param  list<EvalResult>  $results
     */
    private function __construct(
        public Dataset $dataset,
        public array $results,
        public float $durationMs,
    ) {}

    /**
     * @param  array<int, mixed>  $results
     */
    public static function make(Dataset $dataset, array $results, float $durationMs): self
    {
        if ($durationMs < 0.0) {
            throw new InvalidArgumentException(
                sprintf('Duration must be >= 0, got %F.', $durationMs)
            );
        }

        $normalized = [];
        foreach ($results as $index => $result) {
            if (! $result instanceof EvalResult) {
                throw new InvalidArgumentException(
                    sprintf(
                        'results[%d] must be an EvalResult, got %s.',
                        $index,
                        get_debug_type($result),
                    )
                );
            }
            $normalized[] = $result;
        }

        if (count($normalized) > $dataset->count()) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot have more results (%d) than dataset cases (%d).',
                    count($normalized),
                    $dataset->count(),
                )
            );
        }

        return new self($dataset, $normalized, $durationMs);
    }

    public function passed(): bool
    {
        foreach ($this->results as $result) {
            if (! $result->passed()) {
                return false;
            }
        }

        return true;
    }

    public function failed(): bool
    {
        return ! $this->passed();
    }

    public function passedCount(): int
    {
        $count = 0;
        foreach ($this->results as $result) {
            if ($result->passed()) {
                $count++;
            }
        }

        return $count;
    }

    public function failedCount(): int
    {
        return $this->total() - $this->passedCount();
    }

    public function total(): int
    {
        return count($this->results);
    }

    public function passRate(): float
    {
        $total = $this->total();
        if ($total === 0) {
            return 1.0;
        }

        return $this->passedCount() / $total;
    }

    /**
     * @return list<EvalResult>
     */
    public function failures(): array
    {
        $failures = [];
        foreach ($this->results as $result) {
            if ($result->failed()) {
                $failures[] = $result;
            }
        }

        return $failures;
    }
}
