<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

function makeRunForResults(): EvalRun
{
    $dataset = newDataset(['name' => 'r-'.bin2hex(random_bytes(3)), 'case_count' => 1]);

    return newRun([
        'dataset_id' => $dataset->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'callable',
        'passed' => true,
        'pass_count' => 1,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 1,
        'duration_ms' => 1.0,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function newResult(array $attributes): EvalResult
{
    $result = new EvalResult;
    $result->fill($attributes);
    $result->save();

    return $result;
}

it('creates a result with a ULID primary key', function (): void {
    $run = makeRunForResults();

    $result = newResult([
        'run_id' => $run->id,
        'case_index' => 0,
        'input' => ['foo' => 'bar'],
        'passed' => true,
        'assertion_results' => [],
        'duration_ms' => 1.0,
    ]);

    expect($result->id)->toBeString()
        ->and(strlen($result->id))->toBe(26);
});

it('persists and retrieves all expected fields', function (): void {
    $run = makeRunForResults();

    $result = newResult([
        'run_id' => $run->id,
        'case_index' => 0,
        'case_name' => 'greeting',
        'input' => ['prompt' => 'hi'],
        'output' => 'hello',
        'expected' => ['ok' => true],
        'passed' => true,
        'assertion_results' => [
            ['name' => 'contains', 'passed' => true, 'reason' => '', 'score' => null, 'metadata' => []],
        ],
        'error_class' => null,
        'error_message' => null,
        'error_trace' => null,
        'duration_ms' => 12.345,
        'latency_ms' => 8.2,
        'tokens_in' => 100,
        'tokens_out' => 50,
        'cost_usd' => 0.00012,
        'model' => 'claude-haiku-4-5',
    ]);

    $result->refresh();

    expect($result->case_index)->toBe(0)
        ->and($result->case_name)->toBe('greeting')
        ->and($result->output)->toBe('hello')
        ->and($result->model)->toBe('claude-haiku-4-5')
        ->and($result->tokens_in)->toBe(100)
        ->and($result->tokens_out)->toBe(50);
});

it('casts input, expected and assertion_results as arrays', function (): void {
    $run = makeRunForResults();

    $result = newResult([
        'run_id' => $run->id,
        'case_index' => 0,
        'input' => ['k' => 'v'],
        'expected' => ['e' => true],
        'passed' => true,
        'assertion_results' => [['name' => 'x', 'passed' => true]],
        'duration_ms' => 1.0,
    ]);

    $result->refresh();

    expect($result->input)->toBeArray()->toBe(['k' => 'v'])
        ->and($result->expected)->toBeArray()->toBe(['e' => true])
        ->and($result->assertion_results)->toBeArray()
        ->and($result->assertion_results[0]['name'])->toBe('x');
});

it('casts passed as boolean', function (): void {
    $run = makeRunForResults();

    $result = newResult([
        'run_id' => $run->id,
        'case_index' => 0,
        'input' => [],
        'passed' => false,
        'assertion_results' => [],
        'duration_ms' => 1.0,
    ]);

    $result->refresh();

    expect($result->passed)->toBeFalse();
});

it('casts numeric fields with expected types', function (): void {
    $run = makeRunForResults();

    $result = newResult([
        'run_id' => $run->id,
        'case_index' => 0,
        'input' => [],
        'passed' => true,
        'assertion_results' => [],
        'duration_ms' => 12.345,
        'latency_ms' => 8.2,
        'tokens_in' => 100,
        'tokens_out' => 50,
        'cost_usd' => 0.00012,
    ]);

    $result->refresh();

    expect($result->case_index)->toBeInt()
        ->and($result->duration_ms)->toBeFloat()->toBe(12.345)
        ->and($result->latency_ms)->toBeFloat()->toBe(8.2)
        ->and($result->tokens_in)->toBeInt()
        ->and($result->tokens_out)->toBeInt()
        ->and($result->cost_usd)->toBeFloat();
});

it('belongs to a run', function (): void {
    $run = makeRunForResults();

    $result = newResult([
        'run_id' => $run->id,
        'case_index' => 0,
        'input' => [],
        'passed' => true,
        'assertion_results' => [],
        'duration_ms' => 1.0,
    ]);

    expect($result->run())->toBeInstanceOf(BelongsTo::class)
        ->and($result->run?->id)->toBe($run->id);
});
