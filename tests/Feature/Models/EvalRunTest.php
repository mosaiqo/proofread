<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * @param  array<string, mixed>  $attributes
 */
function newRunResult(array $attributes): EvalResult
{
    $result = new EvalResult;
    $result->fill($attributes);
    $result->save();

    return $result;
}

function makeDataset(string $name = 'd'): EvalDataset
{
    return newDataset(['name' => $name, 'case_count' => 3]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function makeRun(EvalDataset $dataset, array $overrides = []): EvalRun
{
    return newRun(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'callable',
        'passed' => true,
        'pass_count' => 3,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 3,
        'duration_ms' => 42.5,
    ], $overrides));
}

it('creates a run with a ULID primary key', function (): void {
    $run = makeRun(makeDataset());

    expect($run->id)->toBeString()
        ->and(strlen($run->id))->toBe(26);
});

it('persists and retrieves all expected fields', function (): void {
    $dataset = makeDataset('persist');

    $run = newRun([
        'dataset_id' => $dataset->id,
        'dataset_name' => 'persist',
        'suite_class' => 'App\\Suites\\Foo',
        'subject_type' => 'agent',
        'subject_class' => 'App\\Agents\\Bar',
        'commit_sha' => 'deadbeef',
        'model' => 'claude-haiku-4-5',
        'passed' => false,
        'pass_count' => 2,
        'fail_count' => 1,
        'error_count' => 0,
        'total_count' => 3,
        'duration_ms' => 123.456,
        'total_cost_usd' => 0.012345,
        'total_tokens_in' => 1000,
        'total_tokens_out' => 500,
    ]);

    $run->refresh();

    expect($run->suite_class)->toBe('App\\Suites\\Foo')
        ->and($run->subject_type)->toBe('agent')
        ->and($run->subject_class)->toBe('App\\Agents\\Bar')
        ->and($run->commit_sha)->toBe('deadbeef')
        ->and($run->model)->toBe('claude-haiku-4-5')
        ->and($run->total_cost_usd)->toBe(0.012345)
        ->and($run->total_tokens_in)->toBe(1000)
        ->and($run->total_tokens_out)->toBe(500);
});

it('casts the passed column as boolean', function (): void {
    $run = makeRun(makeDataset(), ['passed' => true]);
    $run->refresh();

    expect($run->passed)->toBeTrue();
});

it('casts integer count columns as integers', function (): void {
    $run = makeRun(makeDataset());
    $run->refresh();

    expect($run->pass_count)->toBeInt()
        ->and($run->fail_count)->toBeInt()
        ->and($run->error_count)->toBeInt()
        ->and($run->total_count)->toBeInt();
});

it('casts duration and cost as floats', function (): void {
    $run = makeRun(makeDataset(), ['duration_ms' => 12.345, 'total_cost_usd' => 0.000123]);
    $run->refresh();

    expect($run->duration_ms)->toBeFloat()->toBe(12.345)
        ->and($run->total_cost_usd)->toBeFloat()->toBe(0.000123);
});

it('belongs to a dataset', function (): void {
    $dataset = makeDataset('rel');
    $run = makeRun($dataset);

    expect($run->dataset())->toBeInstanceOf(BelongsTo::class)
        ->and($run->dataset?->id)->toBe($dataset->id);
});

it('has many results', function (): void {
    $run = makeRun(makeDataset('rel-results'));

    newRunResult([
        'run_id' => $run->id,
        'case_index' => 0,
        'input' => ['foo' => 'bar'],
        'passed' => true,
        'assertion_results' => [],
        'duration_ms' => 1.0,
    ]);
    newRunResult([
        'run_id' => $run->id,
        'case_index' => 1,
        'input' => ['foo' => 'baz'],
        'passed' => false,
        'assertion_results' => [],
        'duration_ms' => 1.5,
    ]);

    expect($run->results())->toBeInstanceOf(HasMany::class)
        ->and($run->results()->count())->toBe(2);
});

it('exposes a failures scope for failed results', function (): void {
    $run = makeRun(makeDataset('fail-scope'));

    newRunResult([
        'run_id' => $run->id,
        'case_index' => 0,
        'input' => [],
        'passed' => true,
        'assertion_results' => [],
        'duration_ms' => 1.0,
    ]);
    newRunResult([
        'run_id' => $run->id,
        'case_index' => 1,
        'input' => [],
        'passed' => false,
        'assertion_results' => [],
        'duration_ms' => 1.0,
    ]);
    newRunResult([
        'run_id' => $run->id,
        'case_index' => 2,
        'input' => [],
        'passed' => false,
        'assertion_results' => [],
        'duration_ms' => 1.0,
    ]);

    expect($run->failures())->toBeInstanceOf(Builder::class)
        ->and($run->failures()->count())->toBe(2);
});

it('computes pass_rate', function (): void {
    $dataset = makeDataset('pr');

    $run = newRun([
        'dataset_id' => $dataset->id,
        'dataset_name' => 'pr',
        'subject_type' => 'callable',
        'passed' => false,
        'pass_count' => 3,
        'fail_count' => 1,
        'error_count' => 0,
        'total_count' => 4,
        'duration_ms' => 1.0,
    ]);

    expect($run->passRate())->toBe(0.75);
});

it('returns 1.0 pass_rate when total is zero', function (): void {
    $dataset = makeDataset('pr-empty');

    $run = newRun([
        'dataset_id' => $dataset->id,
        'dataset_name' => 'pr-empty',
        'subject_type' => 'callable',
        'passed' => true,
        'pass_count' => 0,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 0,
        'duration_ms' => 0.0,
    ]);

    expect($run->passRate())->toBe(1.0);
});

it('cascades deletes from dataset down to runs and results', function (): void {
    $dataset = makeDataset('cascade');
    $run = makeRun($dataset);
    newRunResult([
        'run_id' => $run->id,
        'case_index' => 0,
        'input' => [],
        'passed' => true,
        'assertion_results' => [],
        'duration_ms' => 1.0,
    ]);

    expect(EvalRun::query()->count())->toBe(1)
        ->and(EvalResult::query()->count())->toBe(1);

    $dataset->delete();

    expect(EvalRun::query()->count())->toBe(0)
        ->and(EvalResult::query()->count())->toBe(0);
});
