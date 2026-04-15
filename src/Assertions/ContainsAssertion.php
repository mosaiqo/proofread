<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

final readonly class ContainsAssertion implements Assertion
{
    private function __construct(
        public string $needle,
        public bool $caseSensitive = true,
    ) {}

    public static function make(string $needle, bool $caseSensitive = true): self
    {
        return new self($needle, $caseSensitive);
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        if (! is_string($output)) {
            return AssertionResult::fail(
                sprintf('Expected string output, got %s', gettype($output))
            );
        }

        $haystack = $this->caseSensitive ? $output : mb_strtolower($output);
        $needle = $this->caseSensitive ? $this->needle : mb_strtolower($this->needle);

        $found = $needle === '' || str_contains($haystack, $needle);

        return $found
            ? AssertionResult::pass(sprintf('Output contains "%s"', $this->needle))
            : AssertionResult::fail(sprintf('Output does not contain "%s"', $this->needle));
    }

    public function name(): string
    {
        return 'contains';
    }
}
