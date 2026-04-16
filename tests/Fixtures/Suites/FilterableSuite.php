<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class FilterableSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('filterable', [
            ['input' => 'foo one', 'meta' => ['name' => 'foo-alpha']],
            ['input' => 'foo two', 'meta' => ['name' => 'foo-beta']],
            ['input' => 'bar three', 'meta' => ['name' => 'bar-gamma']],
        ]);
    }

    public function subject(): mixed
    {
        return static fn (string $input): string => $input;
    }

    public function assertions(): array
    {
        return [ContainsAssertion::make(' ')];
    }
}
