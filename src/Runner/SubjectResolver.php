<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Runner;

use Closure;
use Illuminate\Container\Container;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use Mosaiqo\Proofread\Cli\CliInvocation;
use Mosaiqo\Proofread\Cli\CliSubject;
use Mosaiqo\Proofread\Pricing\PricingTable;

final class SubjectResolver
{
    public function __construct(
        private readonly ?PricingTable $pricing = null,
    ) {}

    public function resolve(mixed $subject): Closure
    {
        if ($subject instanceof Closure) {
            return $this->wrapCallable($subject);
        }

        if ($subject instanceof Agent) {
            return $this->wrapAgent($subject);
        }

        if ($subject instanceof CliSubject) {
            return $this->wrapCliSubject($subject);
        }

        if (is_string($subject)) {
            return $this->resolveString($subject);
        }

        if (is_callable($subject)) {
            return $this->wrapCallable(Closure::fromCallable($subject));
        }

        throw new InvalidArgumentException(sprintf(
            'Subject must be a callable, an %s instance, or a class-string of an %s; got %s.',
            Agent::class,
            Agent::class,
            get_debug_type($subject),
        ));
    }

    private function resolveString(string $subject): Closure
    {
        if (is_callable($subject)) {
            return $this->wrapCallable(Closure::fromCallable($subject));
        }

        if (! class_exists($subject)) {
            throw new InvalidArgumentException(sprintf(
                'Subject string "%s" is neither a callable nor an existing class.',
                $subject,
            ));
        }

        if (! is_a($subject, Agent::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Subject class "%s" must implement %s.',
                $subject,
                Agent::class,
            ));
        }

        return function (mixed $input, array $case) use ($subject): SubjectInvocation {
            unset($case);

            /** @var Agent $agent */
            $agent = Container::getInstance()->make($subject);

            return $this->invokeAgent($agent, $input);
        };
    }

    private function wrapCallable(Closure $callable): Closure
    {
        return function (mixed $input, array $case) use ($callable): SubjectInvocation {
            return SubjectInvocation::make($callable($input, $case));
        };
    }

    private function wrapCliSubject(CliSubject $subject): Closure
    {
        return function (mixed $input, array $case) use ($subject): SubjectInvocation {
            unset($case);

            $prompt = is_string($input) ? $input : (string) json_encode($input, JSON_UNESCAPED_UNICODE);

            /** @var CliInvocation $invocation */
            $invocation = $subject($prompt);

            return SubjectInvocation::make(
                $invocation->output,
                [
                    'latency_ms' => $invocation->durationMs,
                    'tokens_in' => $invocation->metadata['tokens_in'] ?? null,
                    'tokens_out' => $invocation->metadata['tokens_out'] ?? null,
                    'tokens_total' => $invocation->metadata['tokens_total'] ?? null,
                    'cost_usd' => $invocation->metadata['cost_usd'] ?? null,
                    'model' => $invocation->metadata['model'] ?? null,
                    'provider' => 'cli',
                    'raw' => $invocation,
                ],
            );
        };
    }

    private function wrapAgent(Agent $agent): Closure
    {
        return function (mixed $input, array $case) use ($agent): SubjectInvocation {
            unset($case);

            return $this->invokeAgent($agent, $input);
        };
    }

    private function invokeAgent(Agent $agent, mixed $input): SubjectInvocation
    {
        $prompt = is_string($input) ? $input : (string) json_encode($input);

        $response = $agent->prompt($prompt);

        return SubjectInvocation::make(
            $response->text,
            $this->metadataFromResponse($response),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFromResponse(AgentResponse $response): array
    {
        $usage = $response->usage;
        $meta = $response->meta;

        $tokensIn = $usage->promptTokens;
        $tokensOut = $usage->completionTokens;
        $cacheReadTokens = $usage->cacheReadInputTokens;
        $cacheWriteTokens = $usage->cacheWriteInputTokens;
        $reasoningTokens = $usage->reasoningTokens;

        return [
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'tokens_total' => $tokensIn + $tokensOut,
            'cache_read_tokens' => $cacheReadTokens,
            'cache_write_tokens' => $cacheWriteTokens,
            'reasoning_tokens' => $reasoningTokens,
            'cost_usd' => $this->deriveCost(
                $meta->model,
                $tokensIn,
                $tokensOut,
                $cacheReadTokens,
                $cacheWriteTokens,
                $reasoningTokens,
            ),
            'model' => $meta->model,
            'provider' => $meta->provider,
            'raw' => $response,
        ];
    }

    private function deriveCost(
        ?string $model,
        int $tokensIn,
        int $tokensOut,
        int $cacheReadTokens,
        int $cacheWriteTokens,
        int $reasoningTokens,
    ): ?float {
        if ($model === null || $model === '') {
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
}
