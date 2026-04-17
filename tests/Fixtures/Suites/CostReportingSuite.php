<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Tests\Fixtures\Assertions\CostReportingAssertion;

final class CostReportingSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('cost-reporting', [
            ['input' => 'alpha', 'meta' => ['name' => 'alpha-case']],
            ['input' => 'beta', 'meta' => ['name' => 'beta-case']],
        ]);
    }

    public function subject(): mixed
    {
        return static fn (string $input): string => $input;
    }

    public function assertions(): array
    {
        return [CostReportingAssertion::make(0.0060, true)];
    }
}
