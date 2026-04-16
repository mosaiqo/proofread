<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class ManyFailuresSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        $cases = [];
        for ($i = 0; $i < 15; $i++) {
            $cases[] = [
                'input' => 'nope-'.$i,
                'meta' => ['name' => 'case-'.$i],
            ];
        }

        return Dataset::make('many_failures', $cases);
    }

    public function subject(): mixed
    {
        return static fn (string $input): string => $input;
    }

    public function assertions(): array
    {
        return [ContainsAssertion::make('unreachable-needle')];
    }
}
