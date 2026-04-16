<?php

declare(strict_types=1);

use Laravel\Ai\Embeddings;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Mosaiqo\Proofread\Assertions\Similar;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Similarity\Similarity;
use Mosaiqo\Proofread\Support\AssertionResult;

beforeEach(function (): void {
    config()->set('ai.default', 'openai');
    config()->set('ai.default_for_embeddings', 'openai');
    config()->set('proofread.similarity.default_model', 'default-embed');
});

it('passes when similarity meets the default threshold', function (): void {
    Embeddings::fake([
        [[1.0, 0.0], [0.9, sqrt(1.0 - 0.81)]],
    ]);

    $result = Similar::to('reference')->run('candidate');

    expect($result)->toBeInstanceOf(AssertionResult::class)
        ->and($result->passed)->toBeTrue()
        ->and($result->score)->toEqualWithDelta(0.9, 0.0001);
});

it('passes when similarity equals the threshold', function (): void {
    Embeddings::fake([
        [[1.0, 0.0], [0.8, 0.6]],
    ]);

    $result = Similar::to('reference')->minScore(0.8)->run('candidate');

    expect($result->passed)->toBeTrue()
        ->and($result->score)->toEqualWithDelta(0.8, 0.0001);
});

it('fails when similarity is below threshold', function (): void {
    Embeddings::fake([
        [[1.0, 0.0], [0.5, sqrt(1.0 - 0.25)]],
    ]);

    $result = Similar::to('reference')->run('candidate');

    expect($result->passed)->toBeFalse()
        ->and($result->score)->toEqualWithDelta(0.5, 0.0001)
        ->and($result->reason)->toContain('0.5')
        ->and($result->reason)->toContain('0.8');
});

it('fails when output is not a string', function (): void {
    $resultInt = Similar::to('reference')->run(42);
    $resultArray = Similar::to('reference')->run(['a', 'b']);
    $resultNull = Similar::to('reference')->run(null);

    expect($resultInt->passed)->toBeFalse()
        ->and($resultInt->reason)->toContain('string')
        ->and($resultArray->passed)->toBeFalse()
        ->and($resultNull->passed)->toBeFalse();
});

it('uses the default embedding model', function (): void {
    $captured = null;
    Embeddings::fake(function ($prompt) use (&$captured): array {
        $captured = $prompt->model;

        return [[1.0, 0.0], [1.0, 0.0]];
    });

    Similar::to('reference')->run('candidate');

    expect($captured)->toBe('default-embed');
});

it('uses the override model via using()', function (): void {
    $captured = null;
    Embeddings::fake(function ($prompt) use (&$captured): array {
        $captured = $prompt->model;

        return [[1.0, 0.0], [1.0, 0.0]];
    });

    Similar::to('reference')->using('override-embed')->run('candidate');

    expect($captured)->toBe('override-embed');
});

it('is immutable via using() and minScore()', function (): void {
    $base = Similar::to('reference');
    $withModel = $base->using('other-model');
    $withMin = $base->minScore(0.5);

    expect($withModel)->not->toBe($base)
        ->and($withMin)->not->toBe($base);
});

it('rejects empty reference in to()', function (): void {
    Similar::to('');
})->throws(InvalidArgumentException::class);

it('rejects empty model in using()', function (): void {
    Similar::to('reference')->using('');
})->throws(InvalidArgumentException::class);

it('rejects minScore above 1', function (): void {
    Similar::to('reference')->minScore(1.5);
})->throws(InvalidArgumentException::class);

it('rejects minScore below -1', function (): void {
    Similar::to('reference')->minScore(-1.5);
})->throws(InvalidArgumentException::class);

it('formats the reason with the cosine and threshold', function (): void {
    Embeddings::fake([
        [[1.0, 0.0], [0.95, sqrt(1.0 - 0.9025)]],
    ]);

    $result = Similar::to('reference')->minScore(0.9)->run('candidate');

    expect($result->passed)->toBeTrue()
        ->and($result->reason)->toContain('0.95')
        ->and($result->reason)->toContain('0.9');
});

it('exposes name as "similar"', function (): void {
    expect(Similar::to('reference')->name())->toBe('similar');
});

it('implements the Assertion contract', function (): void {
    expect(Similar::to('reference'))->toBeInstanceOf(Assertion::class);
});

it('includes embedding metadata in the result', function (): void {
    Embeddings::fake(function ($prompt): EmbeddingsResponse {
        return new EmbeddingsResponse(
            [[1.0, 0.0], [1.0, 0.0]],
            12,
            new Meta($prompt->provider->name(), $prompt->model),
        );
    });

    $result = Similar::to('reference')->run('candidate');

    expect($result->metadata)
        ->toHaveKey('embedding_model')
        ->toHaveKey('embedding_cost_usd')
        ->toHaveKey('embedding_tokens');
    expect($result->metadata['embedding_model'])->toBe('default-embed');
    expect($result->metadata['embedding_cost_usd'])->toBeNull();
    expect($result->metadata['embedding_tokens'])->toBe(12);
});

it('fails gracefully when Similarity throws', function (): void {
    Embeddings::fake(function (): never {
        throw new RuntimeException('boom');
    });

    $result = Similar::to('reference')->run('candidate');

    expect($result->passed)->toBeFalse()
        ->and($result->score)->toBeNull()
        ->and($result->reason)->toContain('Similarity check failed')
        ->and($result->reason)->toContain('boom');
});

it('sets the cosine as the AssertionResult score', function (): void {
    Embeddings::fake([
        [[1.0, 0.0], [0.92, sqrt(1.0 - 0.8464)]],
    ]);

    $result = Similar::to('reference')->run('candidate');

    expect($result->score)->toEqualWithDelta(0.92, 0.0001);
});

it('resolves Similarity from the container when config changes', function (): void {
    config()->set('proofread.similarity.default_model', 'override-from-config');
    app()->forgetInstance(Similarity::class);

    $captured = null;
    Embeddings::fake(function ($prompt) use (&$captured): array {
        $captured = $prompt->model;

        return [[1.0, 0.0], [1.0, 0.0]];
    });

    Similar::to('reference')->run('candidate');

    expect($captured)->toBe('override-from-config');
});
