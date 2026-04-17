<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Judge\Judge;
use Mosaiqo\Proofread\Judge\JudgeException;
use Mosaiqo\Proofread\Support\JudgeResult;

/**
 * LLM-as-judge assertion that fails when the output contains claims, facts,
 * or details not present in or derivable from the provided ground truth.
 *
 * ## Testing with a faked judge
 *
 * ```php
 * use Mosaiqo\Proofread\Judge\JudgeAgent;
 *
 * beforeEach(function (): void {
 *     JudgeAgent::fake(fn () => json_encode([
 *         'passed' => true,
 *         'score' => 1.0,
 *         'reason' => 'All claims grounded.',
 *     ]));
 * });
 * ```
 */
final readonly class HallucinationAssertion implements Assertion
{
    private function __construct(
        public string $groundTruth,
        public ?string $model,
        public float $minScore,
    ) {
        if ($groundTruth === '') {
            throw new InvalidArgumentException('Hallucination ground truth must not be empty.');
        }

        if ($model !== null && $model === '') {
            throw new InvalidArgumentException('Hallucination model override must not be empty.');
        }

        if ($minScore < 0.0 || $minScore > 1.0) {
            throw new InvalidArgumentException(
                "Hallucination minScore must be between 0.0 and 1.0, got {$minScore}."
            );
        }
    }

    public static function against(string $groundTruth): self
    {
        return new self($groundTruth, null, 1.0);
    }

    public function using(string $model): self
    {
        return new self($this->groundTruth, $model, $this->minScore);
    }

    public function minScore(float $threshold): self
    {
        return new self($this->groundTruth, $this->model, $threshold);
    }

    public function run(mixed $output, array $context = []): JudgeResult
    {
        $judge = Container::getInstance()->make(Judge::class);
        $effectiveModel = $this->model ?? $judge->defaultModel();

        if (! is_string($output)) {
            return JudgeResult::fail(
                reason: sprintf(
                    'HallucinationAssertion requires string output, got %s',
                    gettype($output),
                ),
                judgeModel: $effectiveModel,
            );
        }

        $criteria = $this->buildCriteria();

        try {
            $outcome = $judge->judge($criteria, $output, $this->groundTruth, $this->model);
        } catch (JudgeException $exception) {
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

        /** @var string $resolvedModel */
        $resolvedModel = is_string($metadata['judge_model'] ?? null)
            ? $metadata['judge_model']
            : $effectiveModel;

        if ($verdict->passed && $verdict->score >= $this->minScore) {
            return JudgeResult::pass(
                reason: $verdict->reason,
                score: $verdict->score,
                metadata: $metadata,
                judgeModel: $resolvedModel,
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
                judgeModel: $resolvedModel,
                retryCount: $retryCount,
            );
        }

        return JudgeResult::fail(
            reason: $verdict->reason,
            score: $verdict->score,
            metadata: $metadata,
            judgeModel: $resolvedModel,
            retryCount: $retryCount,
        );
    }

    public function name(): string
    {
        return 'hallucination';
    }

    private function buildCriteria(): string
    {
        return implode("\n", [
            'The OUTPUT must contain only claims, facts, or details that are',
            'directly present in or derivable from the GROUND TRUTH provided',
            'below (shown as INPUT). Any information mentioned in the OUTPUT',
            'that is not supported by the GROUND TRUTH is a hallucination.',
            '',
            'GROUND TRUTH:',
            $this->groundTruth,
        ]);
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
