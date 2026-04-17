<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Assertions\CountAssertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

/**
 * Fixture that proves the CLI routes through runSuite and therefore
 * honors assertionsFor() overrides. The base assertions() list is
 * empty, so the only way this suite fails is if the runner invokes
 * assertionsFor() for each case and executes the per-case assertion.
 */
final class AssertionsForBugSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('assertions-for-bug', [
            [
                'input' => [1, 2, 3],
                'meta' => ['name' => 'short', 'expected_count' => 99],
            ],
        ]);
    }

    public function subject(): mixed
    {
        return static fn (array $input): array => $input;
    }

    public function assertions(): array
    {
        return [];
    }

    public function assertionsFor(array $case): array
    {
        $meta = $case['meta'] ?? [];
        $expected = is_array($meta) ? ($meta['expected_count'] ?? 0) : 0;

        return [CountAssertion::equals(is_int($expected) ? $expected : 0)];
    }
}
