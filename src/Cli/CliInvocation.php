<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Cli;

final readonly class CliInvocation
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $output,
        public string $stdout,
        public string $stderr,
        public int $exitCode,
        public float $durationMs,
        public array $metadata = [],
    ) {}
}
