<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

final readonly class LatencyLimit implements Assertion
{
    private function __construct(
        public float $maxMs,
    ) {
        if ($maxMs <= 0) {
            throw new InvalidArgumentException(
                sprintf('Latency limit must be greater than 0, got %F.', $maxMs)
            );
        }
    }

    public static function under(float $maxMs): self
    {
        return new self($maxMs);
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        if (! array_key_exists('latency_ms', $context)) {
            return AssertionResult::fail(
                "LatencyLimit requires 'latency_ms' in context; runner may not be populating it"
            );
        }

        $latency = $context['latency_ms'];
        if (! is_int($latency) && ! is_float($latency)) {
            return AssertionResult::fail(
                sprintf("LatencyLimit requires numeric 'latency_ms' in context, got %s", gettype($latency))
            );
        }

        $latencyFloat = (float) $latency;

        if ($latencyFloat > $this->maxMs) {
            return AssertionResult::fail(
                sprintf(
                    'Latency %sms exceeds limit of %sms',
                    $this->formatMs($latencyFloat),
                    $this->formatMs($this->maxMs),
                )
            );
        }

        return AssertionResult::pass(
            sprintf(
                'Latency %sms is within limit of %sms',
                $this->formatMs($latencyFloat),
                $this->formatMs($this->maxMs),
            )
        );
    }

    public function name(): string
    {
        return 'latency_limit';
    }

    private function formatMs(float $ms): string
    {
        $rounded = round($ms, 2);
        if ($rounded === floor($rounded)) {
            return (string) (int) $rounded;
        }

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }
}
