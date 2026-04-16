<?php

declare(strict_types=1);

use Laravel\Ai\Embeddings;
use Mosaiqo\Proofread\Clustering\FailureCluster;
use Mosaiqo\Proofread\Clustering\FailureClusterer;
use Mosaiqo\Proofread\Similarity\Similarity;
use Mosaiqo\Proofread\Similarity\SimilarityException;

beforeEach(function (): void {
    config()->set('ai.default', 'openai');
    config()->set('ai.default_for_embeddings', 'openai');
});

/**
 * Build an embeddings fake closure that maps each input string to a
 * deterministic vector via the provided lookup.
 *
 * @param  array<string, array<int, float>>  $vectors
 */
function clusterFake(array $vectors): Closure
{
    return function ($prompt) use ($vectors): array {
        $out = [];
        foreach ($prompt->inputs as $input) {
            if (! array_key_exists($input, $vectors)) {
                throw new RuntimeException(sprintf('No fake vector for input "%s".', $input));
            }
            $out[] = $vectors[$input];
        }

        return $out;
    };
}

function newFailureClusterer(string $model = 'text-embedding-3-small'): FailureClusterer
{
    return new FailureClusterer(new Similarity($model));
}

it('returns empty list for empty signals', function (): void {
    Embeddings::fake(function (): array {
        throw new RuntimeException('Should not be called for empty input.');
    });

    $clusterer = newFailureClusterer();

    expect($clusterer->cluster([]))->toBe([]);
});

it('creates a single cluster for one signal', function (): void {
    Embeddings::fake(clusterFake([
        'only-one' => [1.0, 0.0],
    ]));

    $clusters = newFailureClusterer()->cluster(['only-one']);

    expect($clusters)->toHaveCount(1)
        ->and($clusters[0])->toBeInstanceOf(FailureCluster::class)
        ->and($clusters[0]->representative)->toBe('only-one')
        ->and($clusters[0]->memberIndexes)->toBe([0])
        ->and($clusters[0]->memberSignals)->toBe(['only-one'])
        ->and($clusters[0]->size())->toBe(1);
});

it('groups similar signals into the same cluster', function (): void {
    Embeddings::fake(clusterFake([
        'timeout error' => [1.0, 0.0, 0.0],
        'timeout failure' => [0.99, 0.1, 0.0],
        'parser crashed' => [0.0, 0.0, 1.0],
    ]));

    $clusters = newFailureClusterer()->cluster([
        'timeout error',
        'timeout failure',
        'parser crashed',
    ], threshold: 0.8);

    expect($clusters)->toHaveCount(2)
        ->and($clusters[0]->size())->toBe(2)
        ->and($clusters[0]->memberSignals)->toBe(['timeout error', 'timeout failure'])
        ->and($clusters[1]->size())->toBe(1)
        ->and($clusters[1]->memberSignals)->toBe(['parser crashed']);
});

it('creates separate clusters for dissimilar signals', function (): void {
    Embeddings::fake(clusterFake([
        'a' => [1.0, 0.0, 0.0],
        'b' => [0.0, 1.0, 0.0],
        'c' => [0.0, 0.0, 1.0],
    ]));

    $clusters = newFailureClusterer()->cluster(['a', 'b', 'c'], threshold: 0.5);

    expect($clusters)->toHaveCount(3);
});

it('orders clusters by size descending', function (): void {
    Embeddings::fake(clusterFake([
        'solo' => [0.0, 0.0, 1.0],
        'big-1' => [1.0, 0.0, 0.0],
        'big-2' => [1.0, 0.001, 0.0],
        'big-3' => [0.999, 0.0, 0.0],
    ]));

    $clusters = newFailureClusterer()->cluster([
        'solo',
        'big-1',
        'big-2',
        'big-3',
    ], threshold: 0.9);

    expect($clusters)->toHaveCount(2)
        ->and($clusters[0]->size())->toBe(3)
        ->and($clusters[0]->representative)->toBe('big-1')
        ->and($clusters[1]->size())->toBe(1)
        ->and($clusters[1]->representative)->toBe('solo');
});

it('preserves original signal order within a cluster', function (): void {
    Embeddings::fake(clusterFake([
        'first' => [1.0, 0.0],
        'second' => [0.999, 0.01],
        'third' => [0.998, 0.02],
    ]));

    $clusters = newFailureClusterer()->cluster([
        'first',
        'second',
        'third',
    ], threshold: 0.9);

    expect($clusters)->toHaveCount(1)
        ->and($clusters[0]->memberIndexes)->toBe([0, 1, 2])
        ->and($clusters[0]->memberSignals)->toBe(['first', 'second', 'third']);
});

it('uses the provided threshold to collapse or split clusters', function (): void {
    $vectors = [
        's1' => [1.0, 0.0],
        's2' => [0.7, 0.7],
        's3' => [0.0, 1.0],
    ];

    Embeddings::fake(clusterFake($vectors));
    $loose = newFailureClusterer()->cluster(['s1', 's2', 's3'], threshold: -1.0);

    Embeddings::fake(clusterFake($vectors));
    $tight = newFailureClusterer()->cluster(['s1', 's2', 's3'], threshold: 0.99);

    expect($loose)->toHaveCount(1)
        ->and($loose[0]->size())->toBe(3)
        ->and($tight)->toHaveCount(3);
});

it('uses the default embedding model', function (): void {
    $captured = null;
    Embeddings::fake(function ($prompt) use (&$captured): array {
        $captured = $prompt->model;

        return array_map(fn () => [1.0, 0.0], $prompt->inputs);
    });

    newFailureClusterer('default-embed')->cluster(['a']);

    expect($captured)->toBe('default-embed');
});

it('uses the override model when provided', function (): void {
    $captured = null;
    Embeddings::fake(function ($prompt) use (&$captured): array {
        $captured = $prompt->model;

        return array_map(fn () => [1.0, 0.0], $prompt->inputs);
    });

    newFailureClusterer('default-embed')->cluster(['a'], model: 'override-embed');

    expect($captured)->toBe('override-embed');
});

it('rejects threshold above 1', function (): void {
    Embeddings::fake(function (): array {
        throw new RuntimeException('Should not be called.');
    });

    newFailureClusterer()->cluster(['a'], threshold: 1.5);
})->throws(InvalidArgumentException::class);

it('rejects threshold below -1', function (): void {
    Embeddings::fake(function (): array {
        throw new RuntimeException('Should not be called.');
    });

    newFailureClusterer()->cluster(['a'], threshold: -2.0);
})->throws(InvalidArgumentException::class);

it('handles a single cluster with many members', function (): void {
    $signals = [];
    $vectors = [];
    for ($i = 0; $i < 10; $i++) {
        $sig = 'sig-'.$i;
        $signals[] = $sig;
        $vectors[$sig] = [1.0, $i * 0.0001];
    }

    Embeddings::fake(clusterFake($vectors));

    $clusters = newFailureClusterer()->cluster($signals, threshold: 0.9);

    expect($clusters)->toHaveCount(1)
        ->and($clusters[0]->size())->toBe(10)
        ->and($clusters[0]->memberIndexes)->toBe(range(0, 9));
});

it('includes representative and member signals in each cluster', function (): void {
    Embeddings::fake(clusterFake([
        'rep' => [1.0, 0.0],
        'near' => [0.99, 0.1],
        'far' => [0.0, 1.0],
    ]));

    $clusters = newFailureClusterer()->cluster(['rep', 'near', 'far'], threshold: 0.8);

    expect($clusters[0]->representative)->toBe('rep')
        ->and($clusters[0]->memberSignals)->toBe(['rep', 'near']);
});

it('member indexes match original positions', function (): void {
    Embeddings::fake(clusterFake([
        'a' => [1.0, 0.0],
        'b' => [0.0, 1.0],
        'c' => [0.99, 0.1],
        'd' => [0.0, 0.99],
    ]));

    $clusters = newFailureClusterer()->cluster(['a', 'b', 'c', 'd'], threshold: 0.8);

    expect($clusters)->toHaveCount(2);

    $repToIndexes = [];
    foreach ($clusters as $cluster) {
        $repToIndexes[$cluster->representative] = $cluster->memberIndexes;
    }

    expect($repToIndexes['a'])->toBe([0, 2])
        ->and($repToIndexes['b'])->toBe([1, 3]);
});

it('propagates SimilarityException when embedding fails', function (): void {
    Embeddings::fake(function (): never {
        throw new RuntimeException('embeddings provider down');
    });

    newFailureClusterer()->cluster(['a', 'b']);
})->throws(SimilarityException::class);
