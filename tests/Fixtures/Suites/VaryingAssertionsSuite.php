<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class VaryingAssertionsSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('varying', [
            ['input' => 'hello world', 'meta' => ['name' => 'case-two']],
            ['input' => 'hello world', 'meta' => ['name' => 'case-four']],
        ]);
    }

    public function subject(): mixed
    {
        return static fn (string $input): string => $input;
    }

    public function assertions(): array
    {
        return [
            ContainsAssertion::make('hello'),
            ContainsAssertion::make('world'),
        ];
    }

    public function assertionsFor(array $case): array
    {
        $name = $case['meta']['name'] ?? '';

        if ($name === 'case-four') {
            return [
                ContainsAssertion::make('hello'),
                ContainsAssertion::make('world'),
                ContainsAssertion::make('h'),
                ContainsAssertion::make('w'),
            ];
        }

        return [
            ContainsAssertion::make('hello'),
            ContainsAssertion::make('world'),
        ];
    }
}
