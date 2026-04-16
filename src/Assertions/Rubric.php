<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Judge\Judge;
use Mosaiqo\Proofread\Judge\JudgeAgent;
use Mosaiqo\Proofread\Judge\JudgeException;
use Mosaiqo\Proofread\Support\JudgeResult;

/**
 * LLM-as-judge assertion that scores output against natural-language criteria.
 *
 * ## Testing with a faked judge
 *
 * When testing code that uses this assertion, avoid real LLM calls by faking
 * the judge agent. Proofread uses an internal {@see JudgeAgent}
 * class that extends the Laravel AI SDK's Agent contract, so you can fake it
 * with the SDK's fake helper:
 *
 * ```php
 * use Mosaiqo\Proofread\Judge\JudgeAgent;
 *
 * beforeEach(function () {
 *     JudgeAgent::fake(fn () => json_encode([
 *         'passed' => true,
 *         'score' => 0.95,
 *         'reason' => 'Meets criteria.',
 *     ]));
 * });
 * ```
 *
 * Make sure `config('ai.default')` points to any provider and that
 * `config('proofread.judge.default_model')` is set — both are loaded by the
 * default service provider and are typically already in place.
 */
final readonly class Rubric implements Assertion
{
    private function __construct(
        public string $criteria,
        public ?string $model,
        public float $minScore,
    ) {
        if ($criteria === '') {
            throw new InvalidArgumentException('Rubric criteria must not be empty.');
        }

        if ($model !== null && $model === '') {
            throw new InvalidArgumentException('Rubric model override must not be empty.');
        }

        if ($minScore < 0.0 || $minScore > 1.0) {
            throw new InvalidArgumentException(
                "Rubric minScore must be between 0.0 and 1.0, got {$minScore}."
            );
        }
    }

    public static function make(string $criteria): self
    {
        return new self($criteria, null, 1.0);
    }

    public function using(string $model): self
    {
        return new self($this->criteria, $model, $this->minScore);
    }

    public function minScore(float $threshold): self
    {
        return new self($this->criteria, $this->model, $threshold);
    }

    public function run(mixed $output, array $context = []): JudgeResult
    {
        $judge = Container::getInstance()->make(Judge::class);
        $input = $context['input'] ?? null;

        try {
            $outcome = $judge->judge($this->criteria, $output, $input, $this->model);
        } catch (JudgeException $exception) {
            $effectiveModel = $this->model ?? $judge->defaultModel();

            return JudgeResult::fail(
                reason: sprintf('Judge failed: %s', $exception->getMessage()),
                score: null,
                metadata: [
                    'judge_model' => $effectiveModel,
                    'judge_tokens_in' => null,
                    'judge_tokens_out' => null,
                    'judge_cost_usd' => null,
                    'judge_raw_response' => $exception->lastRawResponse,
                ],
                judgeModel: $effectiveModel,
                retryCount: $exception->attempts,
            );
        }

        $verdict = $outcome['verdict'];
        $metadata = $outcome['metadata'];
        $retryCount = $outcome['retryCount'];

        /** @var string $effectiveModel */
        $effectiveModel = is_string($metadata['judge_model'] ?? null)
            ? $metadata['judge_model']
            : ($this->model ?? $judge->defaultModel());

        if ($verdict->passed && $verdict->score >= $this->minScore) {
            return JudgeResult::pass(
                reason: $verdict->reason,
                score: $verdict->score,
                metadata: $metadata,
                judgeModel: $effectiveModel,
                retryCount: $retryCount,
            );
        }

        if ($verdict->passed) {
            return JudgeResult::fail(
                reason: sprintf(
                    'Judge approved but score %s is below threshold %s',
                    $this->formatScore($verdict->score),
                    $this->formatScore($this->minScore),
                ),
                score: $verdict->score,
                metadata: $metadata,
                judgeModel: $effectiveModel,
                retryCount: $retryCount,
            );
        }

        return JudgeResult::fail(
            reason: $verdict->reason,
            score: $verdict->score,
            metadata: $metadata,
            judgeModel: $effectiveModel,
            retryCount: $retryCount,
        );
    }

    public function name(): string
    {
        return 'rubric';
    }

    private function formatScore(float $score): string
    {
        $rounded = round($score, 2);
        if ($rounded === floor($rounded)) {
            return number_format($rounded, 1);
        }

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }
}
