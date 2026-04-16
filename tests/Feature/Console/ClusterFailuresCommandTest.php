<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Ai\Embeddings;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Models\ShadowEval;

beforeEach(function (): void {
    /** @var Repository $config */
    $config = app('config');
    $config->set('ai.default', 'openai');
    $config->set('ai.default_for_embeddings', 'openai');
});

/**
 * Fake embeddings that return a vector chosen by matching a keyword inside
 * each input string. The first matching keyword wins; unmatched inputs fall
 * back to a distinct "noise" vector.
 *
 * @param  array<string, array<int, float>>  $keywordVectors
 * @param  array<int, float>  $fallback
 */
function clusterCommandFake(array $keywordVectors, array $fallback = [0.0, 0.0, 0.0, 1.0]): Closure
{
    return function ($prompt) use ($keywordVectors, $fallback): array {
        $out = [];
        foreach ($prompt->inputs as $input) {
            $assigned = $fallback;
            foreach ($keywordVectors as $keyword => $vector) {
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

function seedFailedEvalResult(string $dataset, string $needle, ?string $runId = null): EvalResult
{
    if ($runId === null) {
        $runId = seedEvalRunForDataset($dataset);
    }

    $result = new EvalResult;
    $result->fill([
        'run_id' => $runId,
        'case_index' => 0,
        'case_name' => null,
        'input' => ['prompt' => 'classify '.$needle.' document'],
        'output' => 'wrong answer about '.$needle,
        'expected' => null,
        'passed' => false,
        'assertion_results' => [
            [
                'assertion' => 'contains',
                'passed' => false,
                'reason' => sprintf('Output does not contain "%s"', $needle),
            ],
        ],
        'error_class' => null,
        'error_message' => null,
        'error_trace' => null,
        'duration_ms' => 1.0,
        'latency_ms' => 1.0,
        'tokens_in' => 0,
        'tokens_out' => 0,
        'cost_usd' => 0.0,
        'model' => null,
        'created_at' => Carbon::now(),
    ]);
    $result->save();

    return $result;
}

function seedEvalRunForDataset(string $datasetName): string
{
    $dataset = EvalDataset::query()->where('name', $datasetName)->first();
    if ($dataset === null) {
        $dataset = new EvalDataset;
        $dataset->fill([
            'name' => $datasetName,
            'case_count' => 1,
            'checksum' => null,
        ]);
        $dataset->save();
    }

    $run = new EvalRun;
    $run->fill([
        'dataset_id' => $dataset->id,
        'dataset_name' => $datasetName,
        'suite_class' => null,
        'subject_type' => 'agent',
        'subject_class' => null,
        'commit_sha' => null,
        'model' => null,
        'passed' => false,
        'pass_count' => 0,
        'fail_count' => 1,
        'error_count' => 0,
        'total_count' => 1,
        'duration_ms' => 1.0,
        'total_cost_usd' => 0.0,
        'total_tokens_in' => 0,
        'total_tokens_out' => 0,
    ]);
    $run->save();

    return $run->id;
}

function seedFailedShadowEval(string $agentClass, string $needle, ?Carbon $evaluatedAt = null): ShadowEval
{
    $capture = new ShadowCapture;
    $capture->fill([
        'agent_class' => $agentClass,
        'prompt_hash' => bin2hex(random_bytes(32)),
        'input_payload' => ['prompt' => 'classify '.$needle.' input'],
        'output' => 'wrong answer about '.$needle,
        'tokens_in' => 0,
        'tokens_out' => 0,
        'cost_usd' => 0.0,
        'latency_ms' => 1.0,
        'model_used' => null,
        'captured_at' => $evaluatedAt ?? Carbon::now(),
        'sample_rate' => 1.0,
        'is_anonymized' => true,
    ]);
    $capture->save();

    $eval = new ShadowEval;
    $eval->fill([
        'capture_id' => $capture->id,
        'agent_class' => $agentClass,
        'passed' => false,
        'total_assertions' => 1,
        'passed_assertions' => 0,
        'failed_assertions' => 1,
        'assertion_results' => [
            [
                'assertion' => 'contains',
                'passed' => false,
                'reason' => sprintf('Output does not contain "%s"', $needle),
            ],
        ],
        'evaluation_duration_ms' => 1.0,
        'evaluated_at' => $evaluatedAt ?? Carbon::now(),
    ]);
    $eval->save();

    return $eval;
}

it('clusters eval failures by default source', function (): void {
    Embeddings::fake(clusterCommandFake([
        'alpha' => [1.0, 0.0, 0.0, 0.0],
        'beta' => [0.0, 1.0, 0.0, 0.0],
    ]));

    seedFailedEvalResult('ds', 'alpha');
    seedFailedEvalResult('ds', 'alpha');
    seedFailedEvalResult('ds', 'beta');

    $exit = Artisan::call('evals:cluster', ['--threshold' => 0.9]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Found 2 clusters from 3 failures')
        ->and($output)->toContain('Cluster 1')
        ->and($output)->toContain('Cluster 2');
});

it('clusters shadow evals when --source=shadow_evals', function (): void {
    Embeddings::fake(clusterCommandFake([
        'alpha' => [1.0, 0.0, 0.0, 0.0],
        'beta' => [0.0, 1.0, 0.0, 0.0],
    ]));

    seedFailedShadowEval('App\\Agents\\X', 'alpha');
    seedFailedShadowEval('App\\Agents\\X', 'alpha');
    seedFailedShadowEval('App\\Agents\\X', 'beta');

    $exit = Artisan::call('evals:cluster', [
        '--source' => 'shadow_evals',
        '--threshold' => 0.9,
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Found 2 clusters from 3 failures');
});

it('filters by dataset', function (): void {
    Embeddings::fake(clusterCommandFake([
        'alpha' => [1.0, 0.0, 0.0, 0.0],
    ]));

    seedFailedEvalResult('keep-me', 'alpha');
    seedFailedEvalResult('drop-me', 'alpha');

    $exit = Artisan::call('evals:cluster', [
        '--dataset' => 'keep-me',
        '--threshold' => 0.9,
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Found 1 clusters from 1 failures');
});

it('filters by agent on shadow_evals source', function (): void {
    Embeddings::fake(clusterCommandFake([
        'alpha' => [1.0, 0.0, 0.0, 0.0],
    ]));

    seedFailedShadowEval('App\\Agents\\Keep', 'alpha');
    seedFailedShadowEval('App\\Agents\\Drop', 'alpha');

    $exit = Artisan::call('evals:cluster', [
        '--source' => 'shadow_evals',
        '--agent' => 'App\\Agents\\Keep',
        '--threshold' => 0.9,
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Found 1 clusters from 1 failures');
});

it('respects --since filter on shadow evals', function (): void {
    Embeddings::fake(clusterCommandFake([
        'alpha' => [1.0, 0.0, 0.0, 0.0],
    ]));

    seedFailedShadowEval('App\\Agents\\X', 'alpha', Carbon::now()->subDays(2));
    seedFailedShadowEval('App\\Agents\\X', 'alpha', Carbon::now()->subMinutes(5));

    $exit = Artisan::call('evals:cluster', [
        '--source' => 'shadow_evals',
        '--since' => '1h',
        '--threshold' => 0.9,
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Found 1 clusters from 1 failures');
});

it('respects --limit', function (): void {
    Embeddings::fake(clusterCommandFake([
        'alpha' => [1.0, 0.0, 0.0, 0.0],
    ]));

    for ($i = 0; $i < 10; $i++) {
        seedFailedEvalResult('ds', 'alpha');
    }

    $exit = Artisan::call('evals:cluster', [
        '--limit' => 3,
        '--threshold' => 0.9,
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Found 1 clusters from 3 failures');
});

it('prints No failures found when none match', function (): void {
    $exit = Artisan::call('evals:cluster');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('No failures found');
});

it('outputs table format by default', function (): void {
    Embeddings::fake(clusterCommandFake([
        'alpha' => [1.0, 0.0, 0.0, 0.0],
    ]));

    seedFailedEvalResult('ds', 'alpha');
    seedFailedEvalResult('ds', 'alpha');

    $exit = Artisan::call('evals:cluster', ['--threshold' => 0.9]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('[Cluster 1]')
        ->and($output)->toContain('members:');
});

it('outputs JSON with --format=json', function (): void {
    Embeddings::fake(clusterCommandFake([
        'alpha' => [1.0, 0.0, 0.0, 0.0],
        'beta' => [0.0, 1.0, 0.0, 0.0],
    ]));

    seedFailedEvalResult('ds', 'alpha');
    seedFailedEvalResult('ds', 'beta');

    $exit = Artisan::call('evals:cluster', [
        '--format' => 'json',
        '--threshold' => 0.9,
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0);

    $decoded = json_decode($output, true);
    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveCount(2);

    /** @var list<array<string, mixed>> $decoded */
    expect($decoded[0])->toHaveKeys(['representative', 'size', 'members']);
    expect($decoded[0]['members'])->toBeArray()
        ->and($decoded[0]['members'][0])->toHaveKeys(['index', 'signal']);
});

it('uses the threshold from the flag', function (): void {
    Embeddings::fake(clusterCommandFake([
        'alpha' => [1.0, 0.0, 0.0, 0.0],
        'zeta' => [0.9, 0.43589, 0.0, 0.0],
    ]));

    seedFailedEvalResult('ds', 'alpha');
    seedFailedEvalResult('ds', 'zeta');

    Artisan::call('evals:cluster', ['--threshold' => 0.99]);
    $tight = Artisan::output();

    Artisan::call('evals:cluster', ['--threshold' => 0.5]);
    $loose = Artisan::output();

    expect($tight)->toContain('Found 2 clusters from 2 failures')
        ->and($loose)->toContain('Found 1 clusters from 2 failures');
});

it('exits 0 on success', function (): void {
    Embeddings::fake(clusterCommandFake([
        'alpha' => [1.0, 0.0, 0.0, 0.0],
    ]));

    seedFailedEvalResult('ds', 'alpha');

    expect(Artisan::call('evals:cluster', ['--threshold' => 0.9]))->toBe(0);
});

it('includes cluster sizes in output', function (): void {
    Embeddings::fake(clusterCommandFake([
        'alpha' => [1.0, 0.0, 0.0, 0.0],
    ]));

    seedFailedEvalResult('ds', 'alpha');
    seedFailedEvalResult('ds', 'alpha');
    seedFailedEvalResult('ds', 'alpha');

    Artisan::call('evals:cluster', ['--threshold' => 0.9]);
    $output = Artisan::output();

    expect($output)->toContain('3 members');
});
