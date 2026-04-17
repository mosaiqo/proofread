<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Cli;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

abstract class CliSubject
{
    /**
     * Absolute path or name (resolvable via PATH) of the CLI binary.
     */
    abstract public function binary(): string;

    /**
     * Arguments to pass to the binary. Returned as array of strings
     * for shell-safe execution (no shell interpolation).
     *
     * @return list<string>
     */
    abstract public function args(string $prompt): array;

    /**
     * Parse stdout + stderr into the assistant's response and optional
     * metadata. Invoked regardless of exit code — implementations decide
     * whether a non-zero exit is a failure or part of normal output.
     */
    abstract public function parseOutput(string $stdout, string $stderr): CliResponse;

    /**
     * Process timeout in seconds. Default 120s.
     */
    public function timeout(): int
    {
        return 120;
    }

    /**
     * Working directory for the subprocess. Default null = current.
     */
    public function workingDirectory(): ?string
    {
        return null;
    }

    /**
     * Extra environment variables for the subprocess.
     *
     * @return array<string, string>
     */
    public function env(): array
    {
        return [];
    }

    /**
     * Whether to pass the prompt via stdin instead of args. Default false.
     */
    public function usesStdin(): bool
    {
        return false;
    }

    public function __invoke(string $prompt): CliInvocation
    {
        $process = $this->buildProcess($prompt);
        $start = hrtime(true);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new CliTimeoutException(
                sprintf(
                    'CLI subject [%s] timed out after %ds: %s',
                    $this->binary(),
                    $this->timeout(),
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }

        $durationMs = (hrtime(true) - $start) / 1_000_000;

        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();
        $exitCode = $process->getExitCode() ?? -1;

        try {
            $parsed = $this->parseOutput($stdout, $stderr);
        } catch (Throwable $e) {
            throw new CliExecutionException(
                sprintf(
                    'Failed to parse output from [%s] (exit %d): %s',
                    $this->binary(),
                    $exitCode,
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }

        $metadata = array_merge($parsed->metadata, [
            'cli_binary' => $this->binary(),
            'cli_exit_code' => $exitCode,
            'cli_stderr' => mb_substr($stderr, 0, 500),
        ]);

        return new CliInvocation(
            output: $parsed->output,
            stdout: $stdout,
            stderr: $stderr,
            exitCode: $exitCode,
            durationMs: round($durationMs, 3),
            metadata: $metadata,
        );
    }

    /**
     * Construct the Symfony Process. Override in tests to inject a
     * pre-configured Process.
     */
    protected function buildProcess(string $prompt): Process
    {
        $command = [$this->binary(), ...$this->args($prompt)];

        $env = $this->env();

        $process = new Process(
            command: $command,
            cwd: $this->workingDirectory(),
            env: $env === [] ? null : $env,
            timeout: (float) $this->timeout(),
        );

        if ($this->usesStdin()) {
            $process->setInput($prompt);
        }

        return $process;
    }

    /**
     * Estimate token count from a string. Default heuristic: word count / 0.75.
     * Override when the CLI provides real counts.
     */
    public function estimateTokens(string $text): int
    {
        return (int) ceil(str_word_count($text) / 0.75);
    }
}
