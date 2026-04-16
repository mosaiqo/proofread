<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class PassingSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('passing', [
            ['input' => 'hello world', 'meta' => ['name' => 'greeting']],
            ['input' => 'hello there', 'meta' => ['name' => 'salutation']],
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
