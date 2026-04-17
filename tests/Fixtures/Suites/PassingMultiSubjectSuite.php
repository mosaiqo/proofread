<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Suite\MultiSubjectEvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class PassingMultiSubjectSuite extends MultiSubjectEvalSuite
{
    public function name(): string
    {
        return 'passing-multi';
    }

    public function dataset(): Dataset
    {
        return Dataset::make('multi-data', [
            ['input' => 'hello world', 'meta' => ['name' => 'positive']],
            ['input' => 'hello there', 'meta' => ['name' => 'greeting']],
        ]);
    }

    public function assertions(): array
    {
        return [ContainsAssertion::make('hello')];
    }

    public function subjects(): array
    {
        return [
            'haiku' => static fn (string $input): string => $input,
            'sonnet' => static fn (string $input): string => $input,
            'opus' => static fn (string $input): string => $input,
        ];
    }
}
