<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Similarity\Similarity;
use Mosaiqo\Proofread\Similarity\SimilarityException;
use Mosaiqo\Proofread\Support\AssertionResult;

final readonly class Similar implements Assertion
{
    private function __construct(
        public string $reference,
        public ?string $model,
        public float $minScore,
    ) {
        if ($reference === '') {
            throw new InvalidArgumentException('Similar reference must not be empty.');
        }

        if ($model !== null && $model === '') {
            throw new InvalidArgumentException('Similar model override must not be empty.');
        }

        if ($minScore < -1.0 || $minScore > 1.0) {
            throw new InvalidArgumentException(
                "Similar minScore must be between -1.0 and 1.0, got {$minScore}."
            );
        }
    }

    public static function to(string $reference): self
    {
        return new self($reference, null, 0.8);
    }

    public function using(string $model): self
    {
        return new self($this->reference, $model, $this->minScore);
    }

    public function minScore(float $threshold): self
    {
        return new self($this->reference, $this->model, $threshold);
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        if (! is_string($output)) {
            return AssertionResult::fail(
                sprintf('Similar requires string output, got %s', get_debug_type($output))
            );
        }

        $similarity = Container::getInstance()->make(Similarity::class);

        try {
            $outcome = $similarity->cosine($output, $this->reference, $this->model);
        } catch (SimilarityException $exception) {
            return AssertionResult::fail(
                sprintf('Similarity check failed: %s', $exception->getMessage()),
                null,
                [
                    'embedding_model' => $this->model,
                    'embedding_cost_usd' => null,
                    'embedding_tokens' => null,
                ],
            );
        }

        $score = $outcome['score'];
        $metadata = $outcome['metadata'];

        if ($score >= $this->minScore) {
            return AssertionResult::pass(
                sprintf(
                    'Similarity %s meets threshold %s',
                    $this->formatScore($score),
                    $this->formatScore($this->minScore),
                ),
                $score,
                $metadata,
            );
        }

        return AssertionResult::fail(
            sprintf(
                'Similarity %s below threshold %s',
                $this->formatScore($score),
                $this->formatScore($this->minScore),
            ),
            $score,
            $metadata,
        );
    }

    public function name(): string
    {
        return 'similar';
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
