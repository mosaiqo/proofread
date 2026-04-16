<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;
use RuntimeException;

final class ErroringSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('erroring', [
            ['input' => 'whatever', 'meta' => ['name' => 'boom-case']],
        ]);
    }

    public function subject(): mixed
    {
        return static function (string $input): string {
            throw new RuntimeException('subject exploded: '.$input);
        };
    }

    public function assertions(): array
    {
        return [ContainsAssertion::make('whatever')];
    }
}
