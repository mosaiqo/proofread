<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Cli;

use Mosaiqo\Proofread\Cli\CliResponse;
use Mosaiqo\Proofread\Cli\CliSubject;

final class SleepCliSubject extends CliSubject
{
    public function __construct(
        private readonly int $sleepSeconds = 5,
        private readonly int $timeoutSeconds = 1,
    ) {}

    public function binary(): string
    {
        return '/bin/sh';
    }

    public function args(string $prompt): array
    {
        unset($prompt);

        return ['-c', 'sleep '.$this->sleepSeconds];
    }

    public function timeout(): int
    {
        return $this->timeoutSeconds;
    }

    public function parseOutput(string $stdout, string $stderr): CliResponse
    {
        unset($stderr);

        return new CliResponse(output: $stdout);
    }
}
