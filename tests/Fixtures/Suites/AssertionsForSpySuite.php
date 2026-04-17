<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

/**
 * Fixture that records how many times assertionsFor() is invoked, to
 * catch regressions where the CLI header or other code paths would
 * call it more than once per case.
 */
final class AssertionsForSpySuite extends EvalSuite
{
    public static int $callCount = 0;

    public static function reset(): void
    {
        self::$callCount = 0;
    }

    public function dataset(): Dataset
    {
        return Dataset::make('assertions-for-spy', [
            ['input' => 'hello world', 'meta' => ['name' => 'spy-a']],
            ['input' => 'hello world', 'meta' => ['name' => 'spy-b']],
        ]);
    }

    public function subject(): mixed
    {
        return static fn (string $input): string => $input;
    }

    public function assertions(): array
    {
        return [ContainsAssertion::make('hello')];
    }

    public function assertionsFor(array $case): array
    {
        self::$callCount++;

        return $this->assertions();
    }
}
