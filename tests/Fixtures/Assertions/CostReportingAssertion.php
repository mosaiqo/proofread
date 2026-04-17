<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Assertions;

use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

final readonly class CostReportingAssertion implements Assertion
{
    private function __construct(
        public float $costUsd,
        public bool $shouldPass,
    ) {}

    public static function make(float $costUsd, bool $shouldPass = true): self
    {
        return new self($costUsd, $shouldPass);
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        unset($output, $context);

        $metadata = ['cost_usd' => $this->costUsd];

        return $this->shouldPass
            ? AssertionResult::pass('cost reported', null, $metadata)
            : AssertionResult::fail('forced failure', null, $metadata);
    }

    public function name(): string
    {
        return 'cost_reporting';
    }
}
