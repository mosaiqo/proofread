<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Shadow\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Pricing\PricingTable;
use Mosaiqo\Proofread\Shadow\PiiSanitizer;

/**
 * Async persistence of a sampled ShadowCapture. Runs on the queue so that
 * PII sanitization, pricing lookup, hashing, and DB writes never happen in
 * the critical path of the caller's agent invocation.
 *
 * Failures bubble up as normal queued job failures (retries + failed_jobs)
 * to keep production-visible regressions observable.
 */
class PersistShadowCaptureJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly string $agentClass,
        public readonly float $sampleRate,
    ) {
        $queue = config('proofread.shadow.queue', 'default');
        $this->onQueue(is_string($queue) && $queue !== '' ? $queue : 'default');
    }

    public function handle(PiiSanitizer $sanitizer, PricingTable $pricing): void
    {
        /** @var array<string, mixed> $rawInput */
        $rawInput = is_array($this->payload['raw_input'] ?? null) ? $this->payload['raw_input'] : [];

        $promptHash = hash('sha256', (string) json_encode($rawInput));

        $sanitizedInput = $sanitizer->sanitizeInput($rawInput);
        if (! is_array($sanitizedInput)) {
            $sanitizedInput = ['value' => $sanitizedInput];
        }

        $rawOutput = $this->payload['output'] ?? null;
        $sanitizedOutput = is_string($rawOutput) ? $sanitizer->sanitizeOutput($rawOutput) : null;

        $model = $this->payload['model'] ?? null;
        $tokensIn = isset($this->payload['tokens_in']) && is_int($this->payload['tokens_in'])
            ? $this->payload['tokens_in']
            : null;
        $tokensOut = isset($this->payload['tokens_out']) && is_int($this->payload['tokens_out'])
            ? $this->payload['tokens_out']
            : null;

        $cost = null;
        if (is_string($model) && $model !== '' && $tokensIn !== null && $tokensOut !== null) {
            $cost = $pricing->cost($model, $tokensIn, $tokensOut);
        }

        $latencyMs = isset($this->payload['latency_ms']) && (is_int($this->payload['latency_ms']) || is_float($this->payload['latency_ms']))
            ? (float) $this->payload['latency_ms']
            : null;

        $capturedAt = $this->payload['captured_at'] ?? null;
        if ($capturedAt instanceof DateTimeInterface) {
            $capturedAtCarbon = Carbon::instance($capturedAt);
        } else {
            $capturedAtCarbon = Carbon::now();
        }

        ShadowCapture::query()->create([
            'agent_class' => $this->agentClass,
            'prompt_hash' => $promptHash,
            'input_payload' => $sanitizedInput,
            'output' => $sanitizedOutput,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'cost_usd' => $cost,
            'latency_ms' => $latencyMs,
            'model_used' => is_string($model) ? $model : null,
            'captured_at' => $capturedAtCarbon,
            'sample_rate' => $this->sampleRate,
            'is_anonymized' => true,
        ]);
    }
}
