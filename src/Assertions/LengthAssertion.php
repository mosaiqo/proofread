<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

final readonly class LengthAssertion implements Assertion
{
    private function __construct(
        public ?int $min,
        public ?int $max,
    ) {
        if ($min !== null && $min < 0) {
            throw new InvalidArgumentException(
                sprintf('Minimum length must be non-negative, got %d', $min)
            );
        }

        if ($max !== null && $max < 0) {
            throw new InvalidArgumentException(
                sprintf('Maximum length must be non-negative, got %d', $max)
            );
        }

        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException(
                sprintf('Minimum length %d cannot exceed maximum length %d', $min, $max)
            );
        }
    }

    public static function min(int $min): self
    {
        return new self($min, null);
    }

    public static function max(int $max): self
    {
        return new self(null, $max);
    }

    public static function between(int $min, int $max): self
    {
        return new self($min, $max);
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        if (! is_string($output)) {
            return AssertionResult::fail(
                sprintf('Expected string output, got %s', gettype($output))
            );
        }

        $length = mb_strlen($output);

        if ($this->min !== null && $length < $this->min) {
            return AssertionResult::fail(
                sprintf('Output length %d is below minimum %d', $length, $this->min)
            );
        }

        if ($this->max !== null && $length > $this->max) {
            return AssertionResult::fail(
                sprintf('Output length %d exceeds maximum %d', $length, $this->max)
            );
        }

        return AssertionResult::pass(
            sprintf('Output length %d is within bounds', $length)
        );
    }

    public function name(): string
    {
        return 'length';
    }
}
