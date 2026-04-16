<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Similarity;

use InvalidArgumentException;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Throwable;

final class Similarity
{
    public function __construct(
        private readonly string $defaultModel,
    ) {
        if ($defaultModel === '') {
            throw new InvalidArgumentException('Similarity default model must not be empty.');
        }
    }

    public function defaultModel(): string
    {
        return $this->defaultModel;
    }

    /**
     * Compute cosine similarity between two strings using the configured
     * embeddings provider.
     *
     * @return array{score: float, metadata: array{embedding_model: string, embedding_cost_usd: null, embedding_tokens: int|null}}
     */
    public function cosine(string $a, string $b, ?string $model = null): array
    {
        if ($model !== null && $model === '') {
            throw new InvalidArgumentException('Similarity model override must not be empty.');
        }

        $effectiveModel = $model ?? $this->defaultModel;

        try {
            $response = Embeddings::for([$a, $b])->generate(model: $effectiveModel);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SimilarityException(
                sprintf('Embeddings provider failed: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }

        $cosine = $this->computeCosine($response);
        $tokens = $response->tokens > 0 ? $response->tokens : null;

        return [
            'score' => $cosine,
            'metadata' => [
                'embedding_model' => $effectiveModel,
                'embedding_cost_usd' => null,
                'embedding_tokens' => $tokens,
            ],
        ];
    }

    /**
     * Compute cosine similarity between two pre-computed vectors.
     *
     * Zero-magnitude vectors return 0.0 (they have no direction, so they
     * are treated as orthogonal to every other vector). This matches
     * sklearn's cosine_similarity behavior and keeps clustering callers
     * safe from degenerate embeddings.
     *
     * @param  list<float>  $a
     * @param  list<float>  $b
     *
     * @throws SimilarityException if dimensions mismatch or vectors are empty.
     */
    public static function cosineFromVectors(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new SimilarityException(
                sprintf(
                    'Embedding vectors have mismatched dimensions: %d vs %d.',
                    count($a),
                    count($b),
                )
            );
        }

        if ($a === []) {
            throw new SimilarityException('Embedding vectors must not be empty.');
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $index => $av) {
            $bv = $b[$index];
            $dot += $av * $bv;
            $normA += $av * $av;
            $normB += $bv * $bv;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    private function computeCosine(EmbeddingsResponse $response): float
    {
        if (count($response->embeddings) < 2) {
            throw new SimilarityException(
                sprintf(
                    'Embeddings response must contain at least 2 vectors, got %d.',
                    count($response->embeddings),
                )
            );
        }

        $a = [];
        foreach ($response->embeddings[0] as $value) {
            $a[] = (float) $value;
        }

        $b = [];
        foreach ($response->embeddings[1] as $value) {
            $b[] = (float) $value;
        }

        return self::cosineFromVectors($a, $b);
    }
}
