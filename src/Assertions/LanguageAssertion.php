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
 * LLM-as-judge assertion that verifies the output is primarily written in the
 * expected language. Accepts ISO 639-1 codes (`en`, `es`) or common names
 * (`English`, `Spanish`); language identifier is normalized to lowercase.
 */
final readonly class LanguageAssertion implements Assertion
{
    private function __construct(
        public string $language,
        public ?string $model,
    ) {
        if ($language === '') {
            throw new InvalidArgumentException('Language code must not be empty.');
        }

        if ($model !== null && $model === '') {
            throw new InvalidArgumentException('Language model override must not be empty.');
        }
    }

    public static function matches(string $languageCode): self
    {
        if (trim($languageCode) === '') {
            throw new InvalidArgumentException('Language code must not be empty.');
        }

        return new self(strtolower(trim($languageCode)), null);
    }

    public function using(string $model): self
    {
        return new self($this->language, $model);
    }

    public function run(mixed $output, array $context = []): JudgeResult
    {
        $judge = Container::getInstance()->make(Judge::class);
        $effectiveModel = $this->model ?? $judge->defaultModel();

        if (! is_string($output)) {
            return JudgeResult::fail(
                reason: sprintf(
                    'LanguageAssertion requires string output, got %s',
                    gettype($output),
                ),
                judgeModel: $effectiveModel,
            );
        }

        $criteria = $this->buildCriteria();

        try {
            $outcome = $judge->judge($criteria, $output, null, $this->model);
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

        if ($verdict->passed) {
            return JudgeResult::pass(
                reason: $verdict->reason,
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
        return 'language';
    }

    private function buildCriteria(): string
    {
        return implode("\n", [
            sprintf(
                'Determine whether the following text is primarily written in %s.',
                $this->language,
            ),
            'A text is "primarily in" a language when more than 80% of its',
            'meaningful content is in that language.',
        ]);
    }
}
