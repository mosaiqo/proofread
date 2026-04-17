<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Cli;

final readonly class CliResponse
{
    /**
     * @param  array<string, mixed>  $metadata  Arbitrary metadata from the CLI
     *                                          (e.g. tokens_in, tokens_out, total_cost_usd, session_id).
     */
    public function __construct(
        public string $output,
        public array $metadata = [],
    ) {}
}
