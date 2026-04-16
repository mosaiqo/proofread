<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Shadow;

use Closure;
use Illuminate\Support\Carbon;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Mosaiqo\Proofread\Shadow\Jobs\PersistShadowCaptureJob;

/**
 * Production-side middleware that samples real agent invocations and
 * enqueues a job to persist a sanitized ShadowCapture for offline evaluation.
 *
 * The middleware is strictly non-blocking: it never alters the response
 * returned to the caller, and failures in the capture path surface only
 * through the async queue pipeline.
 */
class EvalShadowMiddleware
{
    public function handle(AgentPrompt $prompt, Closure $next): AgentResponse
    {
        if (config('proofread.shadow.enabled') !== true) {
            /** @var AgentResponse $response */
            $response = $next($prompt);

            return $response;
        }

        $agent = $prompt->agent;
        $agentClass = $agent::class;

        $sampleRate = $this->resolveSampleRate($agentClass);

        $start = hrtime(true);

        /** @var AgentResponse $response */
        $response = $next($prompt);

        $latencyMs = (hrtime(true) - $start) / 1_000_000;

        if (! $this->shouldSample($sampleRate)) {
            return $response;
        }

        $payload = $this->buildPayload($prompt, $response, $latencyMs);

        PersistShadowCaptureJob::dispatch($payload, $agentClass, $sampleRate);

        return $response;
    }

    private function resolveSampleRate(string $agentClass): float
    {
        $perAgent = config('proofread.shadow.agents.'.$agentClass.'.sample_rate');

        if (is_int($perAgent) || is_float($perAgent)) {
            return (float) $perAgent;
        }

        $global = config('proofread.shadow.sample_rate', 0.0);

        return is_int($global) || is_float($global) ? (float) $global : 0.0;
    }

    private function shouldSample(float $sampleRate): bool
    {
        if ($sampleRate <= 0.0) {
            return false;
        }

        if ($sampleRate >= 1.0) {
            return true;
        }

        return (mt_rand() / mt_getrandmax()) < $sampleRate;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(AgentPrompt $prompt, AgentResponse $response, float $latencyMs): array
    {
        return [
            'raw_input' => [
                'prompt' => $prompt->prompt,
                'attachments' => $prompt->attachments->all(),
            ],
            'output' => $response->text,
            'tokens_in' => $response->usage->promptTokens,
            'tokens_out' => $response->usage->completionTokens,
            'model' => $response->meta->model ?? $prompt->model,
            'latency_ms' => $latencyMs,
            'captured_at' => Carbon::now(),
        ];
    }
}
