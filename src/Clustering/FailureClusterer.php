<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Clustering;

use InvalidArgumentException;
use Laravel\Ai\Embeddings;
use Mosaiqo\Proofread\Similarity\Similarity;
use Mosaiqo\Proofread\Similarity\SimilarityException;
use Throwable;

/**
 * Group failure signals into clusters of semantically similar items using
 * threshold-based single-pass clustering over embedding cosine similarity.
 *
 * The algorithm scans signals in their original order. For each signal it
 * compares against the representative of every existing cluster: if the
 * similarity meets the threshold, the signal joins that cluster; otherwise
 * a new cluster is seeded. Deterministic, linear in |signals| * |clusters|,
 * and does not require a target cluster count.
 */
final class FailureClusterer
{
    public function __construct(
        private readonly Similarity $similarity,
    ) {}

    /**
     * Cluster failure signals using embedding-based cosine similarity.
     *
     * @param  list<string>  $signals  Human-readable failure descriptions.
     * @param  float  $threshold  Minimum cosine similarity to join an existing cluster.
     * @param  ?string  $model  Embedding model override (defaults to the Similarity-configured model).
     * @return list<FailureCluster>
     */
    public function cluster(array $signals, float $threshold = 0.75, ?string $model = null): array
    {
        if ($threshold < -1.0 || $threshold > 1.0) {
            throw new InvalidArgumentException(sprintf(
                'Threshold must be between -1.0 and 1.0, got %F.',
                $threshold,
            ));
        }

        if ($signals === []) {
            return [];
        }

        $embeddings = $this->embedAll($signals, $model);

        /** @var list<array{representative: string, representativeIndex: int, vector: list<float>, indexes: list<int>}> $buckets */
        $buckets = [];

        foreach ($signals as $index => $signal) {
            $vector = $embeddings[$index];
            $placed = false;

            foreach ($buckets as $bucketKey => $bucket) {
                $score = Similarity::cosineFromVectors($bucket['vector'], $vector);
                if ($score >= $threshold) {
                    $buckets[$bucketKey]['indexes'][] = $index;
                    $placed = true;
                    break;
                }
            }

            if (! $placed) {
                $buckets[] = [
                    'representative' => $signal,
                    'representativeIndex' => $index,
                    'vector' => $vector,
                    'indexes' => [$index],
                ];
            }
        }

        usort(
            $buckets,
            static fn (array $a, array $b): int => count($b['indexes']) <=> count($a['indexes'])
                ?: $a['representativeIndex'] <=> $b['representativeIndex'],
        );

        $clusters = [];
        foreach ($buckets as $bucket) {
            $members = [];
            foreach ($bucket['indexes'] as $i) {
                $members[] = $signals[$i];
            }
            $clusters[] = new FailureCluster(
                representative: $bucket['representative'],
                memberIndexes: $bucket['indexes'],
                memberSignals: $members,
            );
        }

        return $clusters;
    }

    /**
     * Pre-compute embeddings for every signal in a single batch call.
     *
     * @param  list<string>  $signals
     * @return list<list<float>>
     */
    private function embedAll(array $signals, ?string $model): array
    {
        if ($model !== null && $model === '') {
            throw new InvalidArgumentException('Embedding model override must not be empty.');
        }

        $effectiveModel = $model ?? $this->similarity->defaultModel();

        try {
            $response = Embeddings::for($signals)->generate(model: $effectiveModel);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SimilarityException(
                sprintf('Embeddings provider failed: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }

        if (count($response->embeddings) !== count($signals)) {
            throw new SimilarityException(sprintf(
                'Embeddings response size mismatch: expected %d vectors, got %d.',
                count($signals),
                count($response->embeddings),
            ));
        }

        $normalized = [];
        foreach ($response->embeddings as $vector) {
            $floatVector = [];
            foreach ($vector as $value) {
                $floatVector[] = (float) $value;
            }
            $normalized[] = $floatVector;
        }

        return $normalized;
    }
}
