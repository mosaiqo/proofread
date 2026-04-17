<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Cli\Subjects;

use JsonException;
use Mosaiqo\Proofread\Cli\CliResponse;
use Mosaiqo\Proofread\Cli\CliSubject;
use RuntimeException;

final class ClaudeCodeCliSubject extends CliSubject
{
    /**
     * @param  list<string>  $extraArgs
     * @param  array<string, string>  $envVars
     */
    public function __construct(
        private readonly string $binaryPath = 'claude',
        private readonly ?string $model = null,
        private readonly int $timeoutSeconds = 120,
        private readonly bool $dangerouslySkipPermissions = false,
        private readonly array $extraArgs = [],
        private readonly array $envVars = [],
    ) {}

    public static function make(): self
    {
        return new self;
    }

    public function withBinary(string $binary): self
    {
        return new self(
            $binary,
            $this->model,
            $this->timeoutSeconds,
            $this->dangerouslySkipPermissions,
            $this->extraArgs,
            $this->envVars,
        );
    }

    public function withModel(string $model): self
    {
        return new self(
            $this->binaryPath,
            $model,
            $this->timeoutSeconds,
            $this->dangerouslySkipPermissions,
            $this->extraArgs,
            $this->envVars,
        );
    }

    public function withTimeout(int $seconds): self
    {
        return new self(
            $this->binaryPath,
            $this->model,
            $seconds,
            $this->dangerouslySkipPermissions,
            $this->extraArgs,
            $this->envVars,
        );
    }

    public function skipPermissions(bool $skip = true): self
    {
        return new self(
            $this->binaryPath,
            $this->model,
            $this->timeoutSeconds,
            $skip,
            $this->extraArgs,
            $this->envVars,
        );
    }

    /**
     * @param  list<string>  $args
     */
    public function withArgs(array $args): self
    {
        return new self(
            $this->binaryPath,
            $this->model,
            $this->timeoutSeconds,
            $this->dangerouslySkipPermissions,
            $args,
            $this->envVars,
        );
    }

    /**
     * @param  array<string, string>  $env
     */
    public function withEnv(array $env): self
    {
        return new self(
            $this->binaryPath,
            $this->model,
            $this->timeoutSeconds,
            $this->dangerouslySkipPermissions,
            $this->extraArgs,
            $env,
        );
    }

    public function binary(): string
    {
        return $this->binaryPath;
    }

    public function args(string $prompt): array
    {
        $args = ['-p', $prompt, '--output-format', 'json'];

        if ($this->model !== null) {
            $args[] = '--model';
            $args[] = $this->model;
        }

        if ($this->dangerouslySkipPermissions) {
            $args[] = '--dangerously-skip-permissions';
        }

        return array_merge($args, $this->extraArgs);
    }

    public function timeout(): int
    {
        return $this->timeoutSeconds;
    }

    public function env(): array
    {
        return $this->envVars;
    }

    public function parseOutput(string $stdout, string $stderr): CliResponse
    {
        $trimmed = trim($stdout);

        if ($trimmed === '') {
            throw new RuntimeException(
                'Empty output from claude CLI. stderr: '.mb_substr($stderr, 0, 300),
            );
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Malformed JSON from claude CLI: '.$e->getMessage());
        }

        if (! is_array($decoded)) {
            throw new RuntimeException(
                'Expected JSON object from claude CLI, got '.gettype($decoded),
            );
        }

        if (($decoded['is_error'] ?? false) === true) {
            $errMsg = is_string($decoded['result'] ?? null) ? $decoded['result'] : 'unknown error';
            throw new RuntimeException('claude CLI reported error: '.$errMsg);
        }

        $result = $decoded['result'] ?? null;
        if (! is_string($result)) {
            throw new RuntimeException('claude CLI response missing "result" string field');
        }

        $usage = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];

        $inputTokens = is_int($usage['input_tokens'] ?? null) ? $usage['input_tokens'] : null;
        $outputTokens = is_int($usage['output_tokens'] ?? null) ? $usage['output_tokens'] : null;

        return new CliResponse(
            output: $result,
            metadata: [
                'tokens_in' => $inputTokens,
                'tokens_out' => $outputTokens,
                'tokens_total' => $inputTokens !== null && $outputTokens !== null
                    ? $inputTokens + $outputTokens
                    : null,
                'cache_read_tokens' => is_int($usage['cache_read_input_tokens'] ?? null)
                    ? $usage['cache_read_input_tokens']
                    : null,
                'cache_creation_tokens' => is_int($usage['cache_creation_input_tokens'] ?? null)
                    ? $usage['cache_creation_input_tokens']
                    : null,
                'cost_usd' => isset($decoded['total_cost_usd']) && is_numeric($decoded['total_cost_usd'])
                    ? (float) $decoded['total_cost_usd']
                    : null,
                'model' => is_string($decoded['model'] ?? null)
                    ? $decoded['model']
                    : $this->resolveModelFromUsage($decoded),
                'session_id' => is_string($decoded['session_id'] ?? null) ? $decoded['session_id'] : null,
                'num_turns' => is_int($decoded['num_turns'] ?? null) ? $decoded['num_turns'] : null,
                'api_duration_ms' => is_int($decoded['duration_api_ms'] ?? null) ? $decoded['duration_api_ms'] : null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function resolveModelFromUsage(array $decoded): ?string
    {
        $modelUsage = $decoded['modelUsage'] ?? null;
        if (is_array($modelUsage) && $modelUsage !== []) {
            $keys = array_keys($modelUsage);
            $first = $keys[0];
            if (is_string($first)) {
                return $first;
            }
        }

        return $this->model;
    }
}
