<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Judge;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\TextResponse;
use Mosaiqo\Proofread\Pricing\PricingTable;
use Throwable;

final class Judge
{
    public function __construct(
        private readonly string $defaultModel,
        private readonly int $maxRetries = 1,
        private readonly ?PricingTable $pricing = null,
    ) {
        if ($defaultModel === '') {
            throw new InvalidArgumentException('Judge default model must not be empty.');
        }

        if ($maxRetries < 0) {
            throw new InvalidArgumentException(
                "Judge maxRetries must be non-negative, got {$maxRetries}."
            );
        }
    }

    public function defaultModel(): string
    {
        return $this->defaultModel;
    }

    /**
     * Invoke the LLM judge for a given criteria + output pair.
     *
     * @return array{verdict: JudgeVerdict, metadata: array<string, mixed>, retryCount: int}
     *
     * @throws JudgeException when the judge fails to produce a well-formed verdict within the retry budget.
     */
    public function judge(
        string $criteria,
        mixed $output,
        mixed $input = null,
        ?string $model = null,
    ): array {
        $effectiveModel = $model ?? $this->defaultModel;
        $prompt = $this->buildPrompt($criteria, $output, $input);

        $attempts = 0;
        $maxAttempts = $this->maxRetries + 1;
        $lastRaw = '';
        $lastError = null;
        $tokensIn = null;
        $tokensOut = null;
        $cacheReadTokens = 0;
        $cacheWriteTokens = 0;
        $reasoningTokens = 0;

        while ($attempts < $maxAttempts) {
            $response = $this->invokeAgent($prompt, $effectiveModel);
            $lastRaw = $response->text;
            [$tokensIn, $tokensOut, $cacheReadTokens, $cacheWriteTokens, $reasoningTokens] = $this->extractTokens($response);

            try {
                $verdict = $this->parseVerdict($lastRaw);

                return [
                    'verdict' => $verdict,
                    'metadata' => [
                        'judge_model' => $effectiveModel,
                        'judge_tokens_in' => $tokensIn,
                        'judge_tokens_out' => $tokensOut,
                        'judge_cache_read_tokens' => $cacheReadTokens,
                        'judge_cache_write_tokens' => $cacheWriteTokens,
                        'judge_reasoning_tokens' => $reasoningTokens,
                        'judge_cost_usd' => $this->deriveCost(
                            $effectiveModel,
                            $tokensIn,
                            $tokensOut,
                            $cacheReadTokens,
                            $cacheWriteTokens,
                            $reasoningTokens,
                        ),
                        'judge_raw_response' => $lastRaw,
                    ],
                    'retryCount' => $attempts,
                ];
            } catch (InvalidArgumentException $exception) {
                $lastError = $exception->getMessage();
                $attempts++;
            }
        }

        throw new JudgeException(
            sprintf(
                'Judge failed to produce a valid verdict after %d attempts: %s',
                $maxAttempts,
                $lastError ?? 'unknown parse error',
            ),
            $lastRaw,
            $maxAttempts,
        );
    }

    private function invokeAgent(string $prompt, string $model): AgentResponse
    {
        $agent = new JudgeAgent;

        try {
            return $agent->prompt($prompt, model: $model);
        } catch (Throwable $exception) {
            throw new JudgeException(
                'Judge invocation failed: '.$exception->getMessage(),
                '',
                1,
            );
        }
    }

    private function parseVerdict(string $raw): JudgeVerdict
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Empty judge response.');
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Judge response is not a JSON object.');
        }

        if (! array_key_exists('passed', $decoded)
            || ! array_key_exists('score', $decoded)
            || ! array_key_exists('reason', $decoded)) {
            throw new InvalidArgumentException('Judge response is missing required keys.');
        }

        $passed = $decoded['passed'];
        $score = $decoded['score'];
        $reason = $decoded['reason'];

        if (! is_bool($passed)) {
            throw new InvalidArgumentException('Judge response "passed" must be a boolean.');
        }

        if (! is_int($score) && ! is_float($score)) {
            throw new InvalidArgumentException('Judge response "score" must be numeric.');
        }

        $scoreFloat = (float) $score;
        if ($scoreFloat < 0.0 || $scoreFloat > 1.0) {
            throw new InvalidArgumentException('Judge response "score" must be between 0 and 1.');
        }

        if (! is_string($reason)) {
            throw new InvalidArgumentException('Judge response "reason" must be a string.');
        }

        return new JudgeVerdict($passed, $scoreFloat, $reason);
    }

    private function deriveCost(
        string $model,
        ?int $tokensIn,
        ?int $tokensOut,
        int $cacheReadTokens,
        int $cacheWriteTokens,
        int $reasoningTokens,
    ): ?float {
        if ($tokensIn === null || $tokensOut === null) {
            return null;
        }

        if ($model === '') {
            return null;
        }

        return $this->pricingTable()->cost(
            $model,
            $tokensIn,
            $tokensOut,
            $cacheReadTokens,
            $cacheWriteTokens,
            $reasoningTokens,
        );
    }

    private function pricingTable(): PricingTable
    {
        return $this->pricing ?? Container::getInstance()->make(PricingTable::class);
    }

    /**
     * @return array{0: int|null, 1: int|null, 2: int, 3: int, 4: int}
     */
    private function extractTokens(TextResponse $response): array
    {
        $usage = $response->usage;
        $promptTokens = $usage->promptTokens;
        $completionTokens = $usage->completionTokens;

        $tokensIn = $promptTokens > 0 ? $promptTokens : null;
        $tokensOut = $completionTokens > 0 ? $completionTokens : null;

        return [
            $tokensIn,
            $tokensOut,
            $usage->cacheReadInputTokens,
            $usage->cacheWriteInputTokens,
            $usage->reasoningTokens,
        ];
    }

    private function buildPrompt(string $criteria, mixed $output, mixed $input): string
    {
        $sections = [
            'You are a rigorous evaluator. Judge whether the OUTPUT satisfies the CRITERIA.',
            '',
            'CRITERIA:',
            $criteria,
        ];

        if ($input !== null) {
            $sections[] = '';
            $sections[] = 'INPUT:';
            $sections[] = $this->stringify($input);
        }

        $sections[] = '';
        $sections[] = 'OUTPUT:';
        $sections[] = $this->stringify($output);
        $sections[] = '';
        $sections[] = 'Respond with ONLY a JSON object matching this exact shape, no preamble or commentary:';
        $sections[] = '{"passed": <boolean>, "score": <number between 0 and 1>, "reason": "<one sentence explanation>"}';

        return implode("\n", $sections);
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
