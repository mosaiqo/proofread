<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Cli;

use Mosaiqo\Proofread\Cli\CliResponse;
use Mosaiqo\Proofread\Cli\CliSubject;

final class EnvCliSubject extends CliSubject
{
    /**
     * @param  array<string, string>  $env
     */
    public function __construct(
        private readonly array $env = [],
    ) {}

    public function binary(): string
    {
        return '/bin/sh';
    }

    public function args(string $prompt): array
    {
        unset($prompt);

        return ['-c', 'printf %s "$PROOFREAD_TEST_VAR"'];
    }

    public function env(): array
    {
        return $this->env;
    }

    public function parseOutput(string $stdout, string $stderr): CliResponse
    {
        unset($stderr);

        return new CliResponse(output: $stdout);
    }
}
