<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Assertions\Rubric;
use Mosaiqo\Proofread\Suite\MultiSubjectEvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

/**
 * Multi-subject suite that uses Rubric assertions so --fake-judge has
 * something to drive.
 */
final class RubricMultiSubjectSuite extends MultiSubjectEvalSuite
{
    public function name(): string
    {
        return 'rubric-multi';
    }

    public function dataset(): Dataset
    {
        return Dataset::make('rubric-multi-data', [
            ['input' => 'alpha', 'meta' => ['name' => 'case-a']],
        ]);
    }

    public function assertions(): array
    {
        return [Rubric::make('output must be plausible')];
    }

    public function subjects(): array
    {
        return [
            'haiku' => static fn (string $input): string => $input,
            'sonnet' => static fn (string $input): string => $input,
        ];
    }
}
