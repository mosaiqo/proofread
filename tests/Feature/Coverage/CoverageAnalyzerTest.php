<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Laravel\Ai\Embeddings;
use Mosaiqo\Proofread\Clustering\FailureClusterer;
use Mosaiqo\Proofread\Coverage\CaseCoverage;
use Mosaiqo\Proofread\Coverage\CoverageAnalyzer;
use Mosaiqo\Proofread\Coverage\CoverageReport;
use Mosaiqo\Proofread\Coverage\UncoveredCapture;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalDatasetVersion;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Similarity\Similarity;

beforeEach(function (): void {
    config()->set('ai.default', 'openai');
    config()->set('ai.default_for_embeddings', 'openai');
});

/**
 * Build an Embeddings fake that returns vectors aligned with the inputs
 * using a deterministic lookup.
 *
 * @param  array<string, array<int, float>>  $vectors
 */
function coverageEmbeddingFake(array $vectors): Closure
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

/**
 * @param  list<array<string, mixed>>  $cases
 */
function seedCoverageDataset(string $name, array $cases): EvalDataset
{
    $dataset = new EvalDataset;
    $dataset->fill([
        'name' => $name,
        'case_count' => count($cases),
        'checksum' => hash('sha256', (string) json_encode($cases)),
    ]);
    $dataset->save();

    $version = new EvalDatasetVersion;
    $version->fill([
        'eval_dataset_id' => $dataset->id,
        'checksum' => hash('sha256', (string) json_encode($cases)),
        'cases' => $cases,
        'case_count' => count($cases),
        'first_seen_at' => Carbon::now(),
    ]);
    $version->save();

    return $dataset;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function seedCoverageCapture(array $overrides = []): ShadowCapture
{
    $defaults = [
        'agent_class' => 'App\\Agents\\SupportAgent',
        'prompt_hash' => bin2hex(random_bytes(32)),
        'input_payload' => ['prompt' => 'hi'],
        'output' => 'ok',
        'tokens_in' => 100,
        'tokens_out' => 50,
        'cost_usd' => null,
        'latency_ms' => 10.0,
        'model_used' => 'claude-sonnet-4-6',
        'captured_at' => Carbon::now(),
        'sample_rate' => 1.0,
        'is_anonymized' => true,
    ];

    $attributes = array_merge($defaults, $overrides);

    $capture = new ShadowCapture;
    $capture->fill($attributes);
    $capture->save();

    return $capture;
}

function makeCoverageAnalyzer(string $model = 'text-embedding-3-small'): CoverageAnalyzer
{
    $similarity = new Similarity($model);
    $clusterer = new FailureClusterer($similarity);

    return new CoverageAnalyzer($similarity, $clusterer);
}

it('analyzes coverage for an agent against a dataset', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCoverageDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
        ['input' => 'refund', 'meta' => ['name' => 'refund']],
        ['input' => 'shipping', 'meta' => ['name' => 'shipping']],
    ]);

    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap-greet']]);
    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap-refund']]);
    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap-shipping']]);
    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap-unknown-1']]);
    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap-unknown-2']]);

    Embeddings::fake(coverageEmbeddingFake([
        'greet' => [1.0, 0.0, 0.0],
        'refund' => [0.0, 1.0, 0.0],
        'shipping' => [0.0, 0.0, 1.0],
        '{"prompt":"cap-greet"}' => [0.99, 0.1, 0.0],
        '{"prompt":"cap-refund"}' => [0.1, 0.99, 0.0],
        '{"prompt":"cap-shipping"}' => [0.0, 0.1, 0.99],
        '{"prompt":"cap-unknown-1"}' => [-1.0, 0.0, 0.0],
        '{"prompt":"cap-unknown-2"}' => [-1.0, 0.0, 0.0],
    ]));

    $report = makeCoverageAnalyzer()->analyze(
        agentClass: $agent,
        datasetName: 'support-v1',
        from: Carbon::now()->subDay(),
        to: Carbon::now()->addMinute(),
        threshold: 0.7,
    );

    expect($report)->toBeInstanceOf(CoverageReport::class)
        ->and($report->agentClass)->toBe($agent)
        ->and($report->datasetName)->toBe('support-v1')
        ->and($report->totalCaptures)->toBe(5)
        ->and($report->coveredCount)->toBe(3)
        ->and($report->uncoveredCount)->toBe(2)
        ->and($report->skippedCount)->toBe(0)
        ->and($report->threshold)->toBe(0.7);
});

it('identifies which cases are covered by how many captures', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCoverageDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
        ['input' => 'refund', 'meta' => ['name' => 'refund']],
    ]);

    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap-greet-1']]);
    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap-greet-2']]);
    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap-refund-1']]);

    Embeddings::fake(coverageEmbeddingFake([
        'greet' => [1.0, 0.0],
        'refund' => [0.0, 1.0],
        '{"prompt":"cap-greet-1"}' => [0.99, 0.1],
        '{"prompt":"cap-greet-2"}' => [0.98, 0.2],
        '{"prompt":"cap-refund-1"}' => [0.1, 0.99],
    ]));

    $report = makeCoverageAnalyzer()->analyze(
        agentClass: $agent,
        datasetName: 'support-v1',
        from: Carbon::now()->subDay(),
        to: Carbon::now()->addMinute(),
        threshold: 0.7,
    );

    expect($report->caseCoverage)->toHaveCount(2);

    $byIndex = [];
    foreach ($report->caseCoverage as $cc) {
        $byIndex[$cc->caseIndex] = $cc;
    }

    expect($byIndex[0])->toBeInstanceOf(CaseCoverage::class)
        ->and($byIndex[0]->matchedCaptures)->toBe(2)
        ->and($byIndex[0]->caseName)->toBe('greeting')
        ->and($byIndex[1]->matchedCaptures)->toBe(1)
        ->and($byIndex[1]->caseName)->toBe('refund');
});

it('computes avg similarity per covered case', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCoverageDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);

    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap-1']]);
    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap-2']]);

    Embeddings::fake(coverageEmbeddingFake([
        'greet' => [1.0, 0.0],
        '{"prompt":"cap-1"}' => [1.0, 0.0],
        '{"prompt":"cap-2"}' => [0.6, 0.8],
    ]));

    $report = makeCoverageAnalyzer()->analyze(
        agentClass: $agent,
        datasetName: 'support-v1',
        from: Carbon::now()->subDay(),
        to: Carbon::now()->addMinute(),
        threshold: 0.5,
    );

    expect($report->caseCoverage[0]->matchedCaptures)->toBe(2)
        ->and($report->caseCoverage[0]->avgSimilarity)->toEqualWithDelta(0.8, 0.0001);
});

it('lists uncovered captures with their max similarity', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCoverageDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);

    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap-unknown']]);

    Embeddings::fake(coverageEmbeddingFake([
        'greet' => [1.0, 0.0],
        '{"prompt":"cap-unknown"}' => [0.0, 1.0],
    ]));

    $report = makeCoverageAnalyzer()->analyze(
        agentClass: $agent,
        datasetName: 'support-v1',
        from: Carbon::now()->subDay(),
        to: Carbon::now()->addMinute(),
        threshold: 0.7,
    );

    expect($report->uncoveredCount)->toBe(1)
        ->and($report->uncovered)->toHaveCount(1);

    $uncovered = $report->uncovered[0];
    expect($uncovered)->toBeInstanceOf(UncoveredCapture::class)
        ->and($uncovered->nearestCaseIndex)->toBe(0)
        ->and($uncovered->maxSimilarity)->toEqualWithDelta(0.0, 0.0001)
        ->and($uncovered->inputSnippet)->toContain('cap-unknown');
});

it('clusters uncovered captures', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCoverageDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);

    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'unknown-a']]);
    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'unknown-b']]);
    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'unknown-c']]);
    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'unknown-d']]);

    $snippetA = substr((string) json_encode(['prompt' => 'unknown-a']), 0, 200);
    $snippetB = substr((string) json_encode(['prompt' => 'unknown-b']), 0, 200);
    $snippetC = substr((string) json_encode(['prompt' => 'unknown-c']), 0, 200);
    $snippetD = substr((string) json_encode(['prompt' => 'unknown-d']), 0, 200);

    Embeddings::fake(coverageEmbeddingFake([
        'greet' => [1.0, 0.0],
        '{"prompt":"unknown-a"}' => [0.0, 1.0],
        '{"prompt":"unknown-b"}' => [0.0, 1.0],
        '{"prompt":"unknown-c"}' => [0.01, 0.999],
        '{"prompt":"unknown-d"}' => [0.02, 0.998],
        $snippetA => [0.0, 1.0],
        $snippetB => [0.0, 1.0],
        $snippetC => [0.01, 0.999],
        $snippetD => [0.02, 0.998],
    ]));

    $report = makeCoverageAnalyzer()->analyze(
        agentClass: $agent,
        datasetName: 'support-v1',
        from: Carbon::now()->subDay(),
        to: Carbon::now()->addMinute(),
        threshold: 0.7,
    );

    expect($report->uncoveredCount)->toBe(4)
        ->and($report->uncoveredClusters)->toHaveCount(1)
        ->and($report->uncoveredClusters[0]->size())->toBe(4);
});

it('respects the date window', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCoverageDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);

    seedCoverageCapture([
        'agent_class' => $agent,
        'input_payload' => ['prompt' => 'recent'],
        'captured_at' => Carbon::now()->subDays(1),
    ]);
    seedCoverageCapture([
        'agent_class' => $agent,
        'input_payload' => ['prompt' => 'old'],
        'captured_at' => Carbon::now()->subDays(40),
    ]);

    Embeddings::fake(coverageEmbeddingFake([
        'greet' => [1.0, 0.0],
        '{"prompt":"recent"}' => [0.99, 0.1],
    ]));

    $report = makeCoverageAnalyzer()->analyze(
        agentClass: $agent,
        datasetName: 'support-v1',
        from: Carbon::now()->subDays(7),
        to: Carbon::now()->addMinute(),
        threshold: 0.7,
    );

    expect($report->totalCaptures)->toBe(1)
        ->and($report->coveredCount)->toBe(1);
});

it('respects maxCaptures limit', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCoverageDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);

    for ($i = 0; $i < 10; $i++) {
        seedCoverageCapture([
            'agent_class' => $agent,
            'input_payload' => ['prompt' => 'cap-'.$i],
        ]);
    }

    Embeddings::fake(function ($prompt): array {
        return array_map(fn () => [1.0, 0.0], $prompt->inputs);
    });

    $report = makeCoverageAnalyzer()->analyze(
        agentClass: $agent,
        datasetName: 'support-v1',
        from: Carbon::now()->subDay(),
        to: Carbon::now()->addMinute(),
        threshold: 0.7,
        maxCaptures: 3,
    );

    expect($report->totalCaptures)->toBe(3);
});

it('skips captures with no usable input', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCoverageDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);

    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap-ok']]);
    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => []]);

    Embeddings::fake(coverageEmbeddingFake([
        'greet' => [1.0, 0.0],
        '{"prompt":"cap-ok"}' => [0.99, 0.1],
    ]));

    $report = makeCoverageAnalyzer()->analyze(
        agentClass: $agent,
        datasetName: 'support-v1',
        from: Carbon::now()->subDay(),
        to: Carbon::now()->addMinute(),
        threshold: 0.7,
    );

    expect($report->totalCaptures)->toBe(2)
        ->and($report->coveredCount)->toBe(1)
        ->and($report->skippedCount)->toBe(1);
});

it('returns empty report when no captures match', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCoverageDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);

    Embeddings::fake(function (): array {
        throw new RuntimeException('Should not be called.');
    });

    $report = makeCoverageAnalyzer()->analyze(
        agentClass: $agent,
        datasetName: 'support-v1',
        from: Carbon::now()->subDay(),
        to: Carbon::now()->addMinute(),
        threshold: 0.7,
    );

    expect($report->totalCaptures)->toBe(0)
        ->and($report->coveredCount)->toBe(0)
        ->and($report->uncoveredCount)->toBe(0);
});

it('returns empty report when dataset has no cases', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCoverageDataset('empty-dataset', []);

    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'cap']]);

    Embeddings::fake(function (): array {
        throw new RuntimeException('Should not be called.');
    });

    $report = makeCoverageAnalyzer()->analyze(
        agentClass: $agent,
        datasetName: 'empty-dataset',
        from: Carbon::now()->subDay(),
        to: Carbon::now()->addMinute(),
        threshold: 0.7,
    );

    expect($report->totalCaptures)->toBe(1)
        ->and($report->coveredCount)->toBe(0)
        ->and($report->uncoveredCount)->toBe(0)
        ->and($report->skippedCount)->toBe(1);
});

it('uses the dataset latest version', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    $oldCases = [['input' => 'old-case', 'meta' => ['name' => 'old']]];
    $newCases = [['input' => 'new-case', 'meta' => ['name' => 'new']]];

    $dataset = new EvalDataset;
    $dataset->fill([
        'name' => 'multi-version',
        'case_count' => 1,
        'checksum' => 'old-checksum',
    ]);
    $dataset->save();

    $oldVersion = new EvalDatasetVersion;
    $oldVersion->fill([
        'eval_dataset_id' => $dataset->id,
        'checksum' => 'old-checksum',
        'cases' => $oldCases,
        'case_count' => 1,
        'first_seen_at' => Carbon::now()->subDays(10),
    ]);
    $oldVersion->save();

    $newVersion = new EvalDatasetVersion;
    $newVersion->fill([
        'eval_dataset_id' => $dataset->id,
        'checksum' => 'new-checksum',
        'cases' => $newCases,
        'case_count' => 1,
        'first_seen_at' => Carbon::now()->subDays(1),
    ]);
    $newVersion->save();

    seedCoverageCapture(['agent_class' => $agent, 'input_payload' => ['prompt' => 'c']]);

    Embeddings::fake(coverageEmbeddingFake([
        'new-case' => [1.0, 0.0],
        '{"prompt":"c"}' => [0.99, 0.1],
    ]));

    $report = makeCoverageAnalyzer()->analyze(
        agentClass: $agent,
        datasetName: 'multi-version',
        from: Carbon::now()->subDays(2),
        to: Carbon::now()->addMinute(),
        threshold: 0.7,
    );

    expect($report->coveredCount)->toBe(1)
        ->and($report->caseCoverage[0]->caseName)->toBe('new');
});

it('throws when dataset does not exist', function (): void {
    Embeddings::fake(function (): array {
        throw new RuntimeException('Should not be called.');
    });

    makeCoverageAnalyzer()->analyze(
        agentClass: 'App\\Agents\\SupportAgent',
        datasetName: 'does-not-exist',
        from: Carbon::now()->subDay(),
        to: Carbon::now()->addMinute(),
    );
})->throws(InvalidArgumentException::class);
