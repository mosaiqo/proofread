<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class FailingSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('failing', [
            ['input' => 'hello world', 'meta' => ['name' => 'first-case']],
            ['input' => 'goodbye', 'meta' => ['name' => 'second-case']],
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
}
