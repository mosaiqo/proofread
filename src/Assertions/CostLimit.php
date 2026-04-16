<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

final readonly class CostLimit implements Assertion
{
    private function __construct(
        public float $maxUsd,
    ) {
        if ($maxUsd <= 0) {
            throw new InvalidArgumentException(
                sprintf('Cost limit must be greater than 0, got %F.', $maxUsd)
            );
        }
    }

    public static function under(float $maxUsd): self
    {
        return new self($maxUsd);
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        if (! array_key_exists('cost_usd', $context)) {
            return AssertionResult::fail(
                "CostLimit requires 'cost_usd' in context; subject may not be reporting cost"
            );
        }

        $cost = $context['cost_usd'];

        if ($cost === null) {
            return AssertionResult::fail(
                "CostLimit requires 'cost_usd' in context; cost tracking not available for this subject"
            );
        }

        if (! is_int($cost) && ! is_float($cost)) {
            return AssertionResult::fail(
                sprintf("CostLimit requires numeric 'cost_usd' in context, got %s", gettype($cost))
            );
        }

        $costFloat = (float) $cost;

        if ($costFloat > $this->maxUsd) {
            return AssertionResult::fail(
                sprintf(
                    'Cost %s exceeds limit of %s',
                    $this->formatUsd($costFloat),
                    $this->formatUsd($this->maxUsd),
                )
            );
        }

        return AssertionResult::pass(
            sprintf(
                'Cost %s is within limit of %s',
                $this->formatUsd($costFloat),
                $this->formatUsd($this->maxUsd),
            )
        );
    }

    public function name(): string
    {
        return 'cost_limit';
    }

    private function formatUsd(float $value): string
    {
        return '$'.number_format($value, 4, '.', '');
    }
}
