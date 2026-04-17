<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Cli;

use Mosaiqo\Proofread\Cli\CliResponse;
use Mosaiqo\Proofread\Cli\CliSubject;
use RuntimeException;

final class ThrowingParseCliSubject extends CliSubject
{
    public function binary(): string
    {
        return '/bin/sh';
    }

    public function args(string $prompt): array
    {
        unset($prompt);

        return ['-c', 'printf hello'];
    }

    public function parseOutput(string $stdout, string $stderr): CliResponse
    {
        unset($stdout, $stderr);

        throw new RuntimeException('parse boom');
    }
}
