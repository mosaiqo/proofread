<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Cli;

use Mosaiqo\Proofread\Cli\CliResponse;
use Mosaiqo\Proofread\Cli\CliSubject;

final class LongStderrCliSubject extends CliSubject
{
    public function binary(): string
    {
        return '/bin/sh';
    }

    public function args(string $prompt): array
    {
        unset($prompt);

        // emit 1000 'x' chars to stderr, then "ok" to stdout
        return ['-c', 'awk \'BEGIN { for (i=0;i<1000;i++) printf "x" > "/dev/stderr" }\'; printf ok'];
    }

    public function parseOutput(string $stdout, string $stderr): CliResponse
    {
        unset($stderr);

        return new CliResponse(output: trim($stdout));
    }
}
