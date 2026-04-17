<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Ai\Embeddings;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalDatasetVersion;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;

beforeEach(function (): void {
    config()->set('ai.default', 'openai');
    config()->set('ai.default_for_embeddings', 'openai');
});

/**
 * @param  array<string, array<int, float>>  $vectors
 * @param  array<int, float>  $fallback
 */
function coverageCommandFake(array $vectors, array $fallback = [0.0, 0.0, 0.0, 1.0]): Closure
{
    return function ($prompt) use ($vectors, $fallback): array {
        $out = [];
        foreach ($prompt->inputs as $input) {
            $assigned = $fallback;
            foreach ($vectors as $keyword => $vector) {
                if (str_contains($input, $keyword)) {
                    $assigned = $vector;
                    break;
                }
            }
            $out[] = $assigned;
        }

        return $out;
    };
}

/**
 * @param  list<array<string, mixed>>  $cases
 */
function seedCoverageCommandDataset(string $name, array $cases): EvalDataset
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
function seedCoverageCommandCapture(array $overrides = []): ShadowCapture
{
    $defaults = [
        'agent_class' => EchoAgent::class,
        'prompt_hash' => bin2hex(random_bytes(32)),
        'input_payload' => ['prompt' => 'hello'],
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

it('analyzes coverage and outputs a table', function (): void {
    seedCoverageCommandDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);
    seedCoverageCommandCapture(['input_payload' => ['prompt' => 'greet-me']]);

    Embeddings::fake(coverageCommandFake([
        'greet-me' => [0.99, 0.1],
        'greet' => [1.0, 0.0],
    ]));

    $exit = Artisan::call('evals:coverage', [
        'agent' => EchoAgent::class,
        'dataset' => 'support-v1',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Coverage report for '.EchoAgent::class)
        ->and($output)->toContain('support-v1')
        ->and($output)->toContain('Total captures');
});

it('outputs JSON with --format=json', function (): void {
    seedCoverageCommandDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);
    seedCoverageCommandCapture(['input_payload' => ['prompt' => 'greet-me']]);

    Embeddings::fake(coverageCommandFake([
        'greet-me' => [0.99, 0.1],
        'greet' => [1.0, 0.0],
    ]));

    $exit = Artisan::call('evals:coverage', [
        'agent' => EchoAgent::class,
        'dataset' => 'support-v1',
        '--format' => 'json',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0);

    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveKeys([
            'agent_class',
            'dataset_name',
            'window',
            'threshold',
            'total_captures',
            'covered_count',
            'uncovered_count',
            'skipped_count',
            'coverage_ratio',
            'case_coverage',
            'uncovered',
            'uncovered_clusters',
        ]);

    /** @var array<string, mixed> $decoded */
    expect($decoded['agent_class'])->toBe(EchoAgent::class)
        ->and($decoded['dataset_name'])->toBe('support-v1')
        ->and($decoded['total_captures'])->toBe(1)
        ->and($decoded['covered_count'])->toBe(1);
});

it('respects --days', function (): void {
    seedCoverageCommandDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);
    seedCoverageCommandCapture([
        'input_payload' => ['prompt' => 'recent'],
        'captured_at' => Carbon::now()->subDays(2),
    ]);
    seedCoverageCommandCapture([
        'input_payload' => ['prompt' => 'old'],
        'captured_at' => Carbon::now()->subDays(40),
    ]);

    Embeddings::fake(coverageCommandFake([
        'recent' => [0.99, 0.1],
        'greet' => [1.0, 0.0],
    ]));

    $exit = Artisan::call('evals:coverage', [
        'agent' => EchoAgent::class,
        'dataset' => 'support-v1',
        '--days' => 7,
        '--format' => 'json',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0);
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($output, true);
    expect($decoded['total_captures'])->toBe(1);
});

it('respects --threshold', function (): void {
    seedCoverageCommandDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);
    seedCoverageCommandCapture(['input_payload' => ['prompt' => 'a']]);

    Embeddings::fake(coverageCommandFake([
        'greet' => [1.0, 0.0],
        '"a"' => [0.6, 0.8],
    ]));

    $exit = Artisan::call('evals:coverage', [
        'agent' => EchoAgent::class,
        'dataset' => 'support-v1',
        '--threshold' => 0.95,
        '--format' => 'json',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0);
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($output, true);
    expect($decoded['covered_count'])->toBe(0)
        ->and($decoded['uncovered_count'])->toBe(1);
});

it('respects --max-captures', function (): void {
    seedCoverageCommandDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);
    for ($i = 0; $i < 5; $i++) {
        seedCoverageCommandCapture(['input_payload' => ['prompt' => 'cap-'.$i]]);
    }

    Embeddings::fake(coverageCommandFake([
        'greet' => [1.0, 0.0],
        'cap-' => [0.99, 0.1],
    ]));

    $exit = Artisan::call('evals:coverage', [
        'agent' => EchoAgent::class,
        'dataset' => 'support-v1',
        '--max-captures' => 2,
        '--format' => 'json',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0);
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($output, true);
    expect($decoded['total_captures'])->toBe(2);
});

it('warns when there are no captures', function (): void {
    seedCoverageCommandDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);

    $exit = Artisan::call('evals:coverage', [
        'agent' => EchoAgent::class,
        'dataset' => 'support-v1',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('No captures found');
});

it('exits 2 when agent does not exist', function (): void {
    seedCoverageCommandDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);

    $exit = Artisan::call('evals:coverage', [
        'agent' => 'App\\NotARealAgent',
        'dataset' => 'support-v1',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('not found');
});

it('exits 2 when dataset does not exist', function (): void {
    $exit = Artisan::call('evals:coverage', [
        'agent' => EchoAgent::class,
        'dataset' => 'does-not-exist',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('does-not-exist');
});

it('lists uncovered clusters when present', function (): void {
    seedCoverageCommandDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);
    seedCoverageCommandCapture(['input_payload' => ['prompt' => 'unknown-alpha']]);
    seedCoverageCommandCapture(['input_payload' => ['prompt' => 'unknown-beta']]);

    Embeddings::fake(coverageCommandFake([
        'greet' => [1.0, 0.0],
        'unknown' => [0.0, 1.0],
    ]));

    $exit = Artisan::call('evals:coverage', [
        'agent' => EchoAgent::class,
        'dataset' => 'support-v1',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Uncovered clusters');
});

it('skips recommendation line when no uncovered clusters', function (): void {
    seedCoverageCommandDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);
    seedCoverageCommandCapture(['input_payload' => ['prompt' => 'greet-me']]);

    Embeddings::fake(coverageCommandFake([
        'greet-me' => [0.99, 0.1],
        'greet' => [1.0, 0.0],
    ]));

    $exit = Artisan::call('evals:coverage', [
        'agent' => EchoAgent::class,
        'dataset' => 'support-v1',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->not->toContain('Recommendation:');
});

it('includes case-level coverage stats', function (): void {
    seedCoverageCommandDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
        ['input' => 'refund', 'meta' => ['name' => 'refund']],
    ]);
    seedCoverageCommandCapture(['input_payload' => ['prompt' => 'greet-me']]);
    seedCoverageCommandCapture(['input_payload' => ['prompt' => 'refund-me']]);

    Embeddings::fake(coverageCommandFake([
        'greet-me' => [0.99, 0.1],
        'refund-me' => [0.1, 0.99],
        'greet' => [1.0, 0.0],
        'refund' => [0.0, 1.0],
    ]));

    $exit = Artisan::call('evals:coverage', [
        'agent' => EchoAgent::class,
        'dataset' => 'support-v1',
        '--format' => 'json',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0);
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($output, true);
    /** @var list<array<string, mixed>> $caseCoverage */
    $caseCoverage = $decoded['case_coverage'];

    expect($caseCoverage)->toHaveCount(2);

    $names = array_map(static fn (array $c): string => (string) $c['name'], $caseCoverage);
    sort($names);
    expect($names)->toBe(['greeting', 'refund']);
});

it('exits 2 when --days is not a positive integer', function (): void {
    seedCoverageCommandDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);

    $exit = Artisan::call('evals:coverage', [
        'agent' => EchoAgent::class,
        'dataset' => 'support-v1',
        '--days' => 0,
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('--days');
});

it('exits 2 when --threshold is out of range', function (): void {
    seedCoverageCommandDataset('support-v1', [
        ['input' => 'greet', 'meta' => ['name' => 'greeting']],
    ]);

    $exit = Artisan::call('evals:coverage', [
        'agent' => EchoAgent::class,
        'dataset' => 'support-v1',
        '--threshold' => '1.5',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('--threshold');
});
