<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Assertions\Rubric;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class RubricEnabledSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('rubric-enabled', [
            ['input' => 'first', 'meta' => ['name' => 'case-one']],
            ['input' => 'second', 'meta' => ['name' => 'case-two']],
        ]);
    }

    public function subject(): mixed
    {
        return static fn (string $input): string => $input;
    }

    public function assertions(): array
    {
        return [Rubric::make('output must be plausible')];
    }
}
