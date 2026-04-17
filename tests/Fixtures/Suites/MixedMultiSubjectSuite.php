<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Suite\MultiSubjectEvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

/**
 * Multi-subject suite where one provider passes every case and the other
 * fails every case. Useful for exit-code / matrix rendering tests.
 */
final class MixedMultiSubjectSuite extends MultiSubjectEvalSuite
{
    public function name(): string
    {
        return 'mixed-multi';
    }

    public function dataset(): Dataset
    {
        return Dataset::make('multi-mixed-data', [
            ['input' => 'hello world', 'meta' => ['name' => 'c1']],
            ['input' => 'hello there', 'meta' => ['name' => 'c2']],
        ]);
    }

    public function assertions(): array
    {
        return [ContainsAssertion::make('hello')];
    }

    public function subjects(): array
    {
        return [
            'good' => static fn (string $input): string => $input,
            'bad' => static fn (string $input): string => 'nope',
        ];
    }
}
