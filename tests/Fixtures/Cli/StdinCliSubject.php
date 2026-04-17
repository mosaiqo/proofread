<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Cli;

use Mosaiqo\Proofread\Cli\CliResponse;
use Mosaiqo\Proofread\Cli\CliSubject;

final class StdinCliSubject extends CliSubject
{
    public function binary(): string
    {
        return '/bin/sh';
    }

    public function args(string $prompt): array
    {
        unset($prompt);

        // cat reads stdin and echoes it to stdout
        return ['-c', 'cat'];
    }

    public function usesStdin(): bool
    {
        return true;
    }

    public function parseOutput(string $stdout, string $stderr): CliResponse
    {
        unset($stderr);

        return new CliResponse(output: $stdout);
    }
}
