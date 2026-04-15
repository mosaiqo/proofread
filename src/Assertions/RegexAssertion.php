<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

final readonly class RegexAssertion implements Assertion
{
    private function __construct(
        public string $pattern,
    ) {}

    public static function make(string $pattern): self
    {
        set_error_handler(static fn (): bool => true);

        try {
            $result = preg_match($pattern, '');
        } finally {
            restore_error_handler();
        }

        if ($result === false || preg_last_error() !== PREG_NO_ERROR) {
            throw new InvalidArgumentException(
                sprintf('Invalid regular expression pattern: %s', $pattern)
            );
        }

        return new self($pattern);
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        if (! is_string($output)) {
            return AssertionResult::fail(
                sprintf('Expected string output, got %s', gettype($output))
            );
        }

        set_error_handler(static fn (): bool => true);

        try {
            $matched = preg_match($this->pattern, $output);
        } finally {
            restore_error_handler();
        }

        if ($matched === false) {
            return AssertionResult::fail(
                sprintf('Regex error while matching %s: %s', $this->pattern, preg_last_error_msg())
            );
        }

        return $matched === 1
            ? AssertionResult::pass(sprintf('Output matches %s', $this->pattern))
            : AssertionResult::fail(sprintf('Output does not match %s', $this->pattern));
    }

    public function name(): string
    {
        return 'regex';
    }
}
