<?php

declare(strict_types=1);

use Laravel\Ai\Embeddings;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Mosaiqo\Proofread\Similarity\Similarity;
use Mosaiqo\Proofread\Similarity\SimilarityException;

beforeEach(function (): void {
    config()->set('ai.default', 'openai');
    config()->set('ai.default_for_embeddings', 'openai');
});

it('computes cosine similarity between two texts', function (): void {
    Embeddings::fake([
        [[1.0, 0.0, 0.0], [0.5, 0.5, 0.0]],
    ]);

    $similarity = new Similarity('text-embedding-3-small');

    $result = $similarity->cosine('a', 'b');

    $expected = sqrt(0.5);
    expect($result['score'])->toBeGreaterThan(0.0)
        ->and($result['score'])->toEqualWithDelta($expected, 0.0001);
});

it('returns cosine of 1.0 for identical vectors', function (): void {
    Embeddings::fake([
        [[0.6, 0.8], [0.6, 0.8]],
    ]);

    $similarity = new Similarity('text-embedding-3-small');

    $result = $similarity->cosine('same', 'same');

    expect($result['score'])->toEqualWithDelta(1.0, 0.0001);
});

it('returns cosine of 0.0 for orthogonal vectors', function (): void {
    Embeddings::fake([
        [[1.0, 0.0], [0.0, 1.0]],
    ]);

    $similarity = new Similarity('text-embedding-3-small');

    $result = $similarity->cosine('a', 'b');

    expect($result['score'])->toEqualWithDelta(0.0, 0.0001);
});

it('returns cosine of -1.0 for opposite vectors', function (): void {
    Embeddings::fake([
        [[1.0, 0.0], [-1.0, 0.0]],
    ]);

    $similarity = new Similarity('text-embedding-3-small');

    $result = $similarity->cosine('a', 'b');

    expect($result['score'])->toEqualWithDelta(-1.0, 0.0001);
});

it('handles different text lengths correctly', function (): void {
    Embeddings::fake([
        [[0.1, 0.2, 0.3, 0.4, 0.5], [0.5, 0.4, 0.3, 0.2, 0.1]],
    ]);

    $similarity = new Similarity('text-embedding-3-small');

    $result = $similarity->cosine('hi', 'a much longer sentence indeed');

    expect($result['score'])->toBeFloat()
        ->and($result['score'])->toBeGreaterThan(0.0)
        ->and($result['score'])->toBeLessThan(1.0);
});

it('uses the default model when none is provided', function (): void {
    $captured = null;
    Embeddings::fake(function ($prompt) use (&$captured): array {
        $captured = $prompt->model;

        return [[1.0, 0.0], [1.0, 0.0]];
    });

    $similarity = new Similarity('default-embed-model');

    $result = $similarity->cosine('a', 'b');

    expect($captured)->toBe('default-embed-model');
    expect($result['metadata']['embedding_model'])->toBe('default-embed-model');
});

it('uses the override model when provided', function (): void {
    $captured = null;
    Embeddings::fake(function ($prompt) use (&$captured): array {
        $captured = $prompt->model;

        return [[1.0, 0.0], [1.0, 0.0]];
    });

    $similarity = new Similarity('default-embed-model');

    $result = $similarity->cosine('a', 'b', model: 'override-embed-model');

    expect($captured)->toBe('override-embed-model');
    expect($result['metadata']['embedding_model'])->toBe('override-embed-model');
});

it('includes the model name in metadata', function (): void {
    Embeddings::fake([
        [[1.0, 0.0], [1.0, 0.0]],
    ]);

    $similarity = new Similarity('my-model');

    $result = $similarity->cosine('a', 'b');

    expect($result['metadata'])->toHaveKey('embedding_model')
        ->and($result['metadata']['embedding_model'])->toBe('my-model');
});

it('leaves embedding_cost_usd as null', function (): void {
    Embeddings::fake([
        [[1.0, 0.0], [1.0, 0.0]],
    ]);

    $similarity = new Similarity('m');

    $result = $similarity->cosine('a', 'b');

    expect($result['metadata'])->toHaveKey('embedding_cost_usd')
        ->and($result['metadata']['embedding_cost_usd'])->toBeNull();
});

it('includes token usage in metadata when the SDK reports it', function (): void {
    Embeddings::fake(function ($prompt): EmbeddingsResponse {
        return new EmbeddingsResponse(
            [[1.0, 0.0], [1.0, 0.0]],
            42,
            new Meta($prompt->provider->name(), $prompt->model),
        );
    });

    $similarity = new Similarity('m');

    $result = $similarity->cosine('a', 'b');

    expect($result['metadata']['embedding_tokens'])->toBe(42);
});

it('leaves embedding_tokens as null when the SDK reports zero', function (): void {
    Embeddings::fake([
        [[1.0, 0.0], [1.0, 0.0]],
    ]);

    $similarity = new Similarity('m');

    $result = $similarity->cosine('a', 'b');

    expect($result['metadata'])->toHaveKey('embedding_tokens')
        ->and($result['metadata']['embedding_tokens'])->toBeNull();
});

it('wraps provider failures in SimilarityException', function (): void {
    Embeddings::fake(function (): never {
        throw new RuntimeException('upstream provider down');
    });

    $similarity = new Similarity('m');

    $similarity->cosine('a', 'b');
})->throws(SimilarityException::class, 'upstream provider down');

it('rejects vectors of mismatched dimensions', function (): void {
    Embeddings::fake([
        [[1.0, 0.0, 0.0], [1.0, 0.0]],
    ]);

    $similarity = new Similarity('m');

    $similarity->cosine('a', 'b');
})->throws(SimilarityException::class);

it('rejects empty default model', function (): void {
    new Similarity('');
})->throws(InvalidArgumentException::class);

it('rejects empty override model', function (): void {
    Embeddings::fake([
        [[1.0, 0.0], [1.0, 0.0]],
    ]);

    $similarity = new Similarity('m');

    $similarity->cosine('a', 'b', model: '');
})->throws(InvalidArgumentException::class);

it('computes cosine between two pre-computed vectors via static helper', function (): void {
    expect(Similarity::cosineFromVectors([1.0, 0.0, 0.0], [0.0, 1.0, 0.0]))
        ->toEqualWithDelta(0.0, 0.0001)
        ->and(Similarity::cosineFromVectors([1.0, 1.0], [1.0, 1.0]))
        ->toEqualWithDelta(1.0, 0.0001);
});

it('rejects vectors of mismatched dimensions via static helper', function (): void {
    Similarity::cosineFromVectors([1.0, 2.0], [1.0, 2.0, 3.0]);
})->throws(SimilarityException::class);

it('rejects empty vectors via static helper', function (): void {
    Similarity::cosineFromVectors([], []);
})->throws(SimilarityException::class);

it('handles negative cosines correctly via static helper', function (): void {
    expect(Similarity::cosineFromVectors([1.0, 0.0], [-1.0, 0.0]))
        ->toEqualWithDelta(-1.0, 0.0001);
});

it('returns zero when a vector is all zeros via static helper', function (): void {
    expect(Similarity::cosineFromVectors([0.0, 0.0, 0.0], [1.0, 2.0, 3.0]))
        ->toBe(0.0)
        ->and(Similarity::cosineFromVectors([1.0, 2.0, 3.0], [0.0, 0.0, 0.0]))
        ->toBe(0.0)
        ->and(Similarity::cosineFromVectors([0.0, 0.0], [0.0, 0.0]))
        ->toBe(0.0);
});
