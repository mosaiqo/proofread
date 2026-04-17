<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Support;

use InvalidArgumentException;

final readonly class EvalComparison
{
    /**
     * @param  array<string, EvalRun>  $runs  Keyed by subject label.
     */
    private function __construct(
        public string $name,
        public Dataset $dataset,
        public array $runs,
        public float $durationMs,
    ) {}

    /**
     * @param  array<int|string, mixed>  $runs
     */
    public static function make(string $name, Dataset $dataset, array $runs, float $durationMs): self
    {
        if ($durationMs < 0.0) {
            throw new InvalidArgumentException(
                sprintf('Duration must be >= 0, got %F.', $durationMs)
            );
        }

        if ($runs === []) {
            throw new InvalidArgumentException('runs must be a non-empty map of subject label to EvalRun.');
        }

        $normalized = [];
        foreach ($runs as $label => $run) {
            if (! is_string($label)) {
                throw new InvalidArgumentException(
                    sprintf('Run label must be a string, got %s.', get_debug_type($label))
                );
            }

            if ($label === '') {
                throw new InvalidArgumentException('Run label must not be empty.');
            }

            if (! $run instanceof EvalRun) {
                throw new InvalidArgumentException(
                    sprintf(
                        'runs[%s] must be an EvalRun, got %s.',
                        $label,
                        get_debug_type($run),
                    )
                );
            }

            $normalized[$label] = $run;
        }

        return new self($name, $dataset, $normalized, $durationMs);
    }

    public function passed(): bool
    {
        foreach ($this->runs as $run) {
            if (! $run->passed()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function subjectLabels(): array
    {
        return array_keys($this->runs);
    }

    public function runForSubject(string $label): ?EvalRun
    {
        return $this->runs[$label] ?? null;
    }

    /**
     * @return array<string, float>
     */
    public function passRates(): array
    {
        $rates = [];
        foreach ($this->runs as $label => $run) {
            $rates[$label] = $run->passRate();
        }

        return $rates;
    }

    /**
     * @return array<string, float|null>
     */
    public function totalCosts(): array
    {
        $totals = [];
        foreach ($this->runs as $label => $run) {
            $totals[$label] = $this->costFor($run);
        }

        return $totals;
    }

    private function costFor(EvalRun $run): ?float
    {
        $sum = null;
        foreach ($run->results as $result) {
            $cost = $this->costFromResult($result);
            if ($cost === null) {
                continue;
            }
            $sum = ($sum ?? 0.0) + $cost;
        }

        return $sum;
    }

    private function costFromResult(EvalResult $result): ?float
    {
        foreach ($result->assertionResults as $assertion) {
            $meta = $assertion->metadata;
            if (isset($meta['cost_usd']) && (is_int($meta['cost_usd']) || is_float($meta['cost_usd']))) {
                return (float) $meta['cost_usd'];
            }
        }

        return null;
    }
}
