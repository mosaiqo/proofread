<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use Countable;
use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

final readonly class CountAssertion implements Assertion
{
    private function __construct(
        public ?int $min,
        public ?int $max,
    ) {
        if ($min !== null && $min < 0) {
            throw new InvalidArgumentException(
                sprintf('Minimum count must be non-negative, got %d', $min)
            );
        }

        if ($max !== null && $max < 0) {
            throw new InvalidArgumentException(
                sprintf('Maximum count must be non-negative, got %d', $max)
            );
        }

        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException(
                sprintf('Minimum count %d cannot exceed maximum count %d', $min, $max)
            );
        }
    }

    public static function equals(int $count): self
    {
        return new self($count, $count);
    }

    public static function atLeast(int $min): self
    {
        return new self($min, null);
    }

    public static function atMost(int $max): self
    {
        return new self(null, $max);
    }

    public static function between(int $min, int $max): self
    {
        return new self($min, $max);
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        if (! is_array($output) && ! $output instanceof Countable) {
            return AssertionResult::fail(
                sprintf('CountAssertion requires array or Countable output, got %s', gettype($output))
            );
        }

        $count = count($output);

        if ($this->min !== null && $count < $this->min) {
            return AssertionResult::fail(
                sprintf('Count %d is below minimum %d', $count, $this->min)
            );
        }

        if ($this->max !== null && $count > $this->max) {
            return AssertionResult::fail(
                sprintf('Count %d exceeds maximum %d', $count, $this->max)
            );
        }

        return AssertionResult::pass(
            sprintf('Count %d is within bounds', $count)
        );
    }

    public function name(): string
    {
        return 'count';
    }
}
