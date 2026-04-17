<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Cli;

use Mosaiqo\Proofread\Cli\CliResponse;
use Mosaiqo\Proofread\Cli\CliSubject;

final class StderrCliSubject extends CliSubject
{
    public function __construct(
        private readonly string $stderrMessage = 'warning',
        private readonly int $exitCode = 0,
    ) {}

    public function binary(): string
    {
        return '/bin/sh';
    }

    public function args(string $prompt): array
    {
        unset($prompt);

        return [
            '-c',
            sprintf(
                'printf %s 1>&2; printf ok; exit %d',
                escapeshellarg($this->stderrMessage),
                $this->exitCode,
            ),
        ];
    }

    public function parseOutput(string $stdout, string $stderr): CliResponse
    {
        unset($stderr);

        return new CliResponse(output: trim($stdout));
    }
}
