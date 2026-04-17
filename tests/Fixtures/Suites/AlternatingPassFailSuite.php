<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Tests\Fixtures\Assertions\AlternatingAssertion;

final class AlternatingPassFailSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('alternating', [
            ['input' => 'one', 'meta' => ['name' => 'case-a']],
            ['input' => 'two', 'meta' => ['name' => 'case-b']],
        ]);
    }

    public function subject(): mixed
    {
        return static fn (string $input): string => $input;
    }

    public function assertions(): array
    {
        return [AlternatingAssertion::make()];
    }
}
