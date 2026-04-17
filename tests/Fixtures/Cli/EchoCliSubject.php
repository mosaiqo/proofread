<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Cli;

use Mosaiqo\Proofread\Cli\CliResponse;
use Mosaiqo\Proofread\Cli\CliSubject;

final class EchoCliSubject extends CliSubject
{
    public function __construct(
        private readonly string $canned = 'echoed',
    ) {}

    public function binary(): string
    {
        return '/bin/sh';
    }

    public function args(string $prompt): array
    {
        unset($prompt);

        return ['-c', 'printf %s '.escapeshellarg($this->canned)];
    }

    public function parseOutput(string $stdout, string $stderr): CliResponse
    {
        unset($stderr);

        return new CliResponse(
            output: trim($stdout),
            metadata: ['tokens_in' => 10, 'tokens_out' => 20],
        );
    }
}
