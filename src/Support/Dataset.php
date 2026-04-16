<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Support;

use InvalidArgumentException;

final readonly class Dataset
{
    /**
     * @param  list<array<string, mixed>>  $cases
     */
    private function __construct(
        public string $name,
        public array $cases,
    ) {}

    /**
     * @param  array<int, mixed>  $cases
     */
    public static function make(string $name, array $cases): self
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Dataset name must not be empty.');
        }

        $normalized = [];
        foreach ($cases as $index => $case) {
            if (! is_array($case)) {
                throw new InvalidArgumentException(
                    sprintf('Case at index %d must be an array, got %s.', $index, gettype($case))
                );
            }

            if (! array_key_exists('input', $case)) {
                throw new InvalidArgumentException(
                    sprintf('Case at index %d is missing the "input" key.', $index)
                );
            }

            if (array_key_exists('meta', $case) && ! is_array($case['meta'])) {
                throw new InvalidArgumentException(
                    sprintf('Case at index %d has non-array "meta".', $index)
                );
            }

            $normalized[] = $case;
        }

        return new self($name, $normalized);
    }

    public function count(): int
    {
        return count($this->cases);
    }

    public function isEmpty(): bool
    {
        return $this->cases === [];
    }
}
