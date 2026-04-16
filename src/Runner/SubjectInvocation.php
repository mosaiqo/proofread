<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Runner;

final readonly class SubjectInvocation
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        public mixed $output,
        public array $metadata,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function make(mixed $output, array $metadata = []): self
    {
        return new self($output, $metadata);
    }
}
