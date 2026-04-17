<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Assertions;

use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

/**
 * Deterministic flakiness simulator.
 *
 * Keyed by (class-wide) an invocation counter per case_index. On odd
 * invocations returns pass, on even returns fail. Used to exercise the
 * per-case stability detection in evals:benchmark without non-determinism.
 */
final class AlternatingAssertion implements Assertion
{
    /**
     * @var array<int, int>
     */
    public static array $invocations = [];

    private function __construct() {}

    public static function make(): self
    {
        return new self;
    }

    public static function reset(): void
    {
        self::$invocations = [];
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        unset($output);

        $index = $context['case_index'] ?? 0;
        $key = is_int($index) ? $index : 0;
        self::$invocations[$key] = (self::$invocations[$key] ?? 0) + 1;

        $count = self::$invocations[$key];
        $passed = ($count % 2) === 1;

        return $passed
            ? AssertionResult::pass(sprintf('alternating pass on invocation %d', $count))
            : AssertionResult::fail(sprintf('alternating fail on invocation %d', $count));
    }

    public function name(): string
    {
        return 'alternating';
    }
}
