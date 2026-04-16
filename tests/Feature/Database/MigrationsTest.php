<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('creates the eval_datasets table with expected columns', function (): void {
    expect(Schema::hasTable('eval_datasets'))->toBeTrue();

    foreach (['id', 'name', 'case_count', 'checksum', 'created_at', 'updated_at'] as $column) {
        expect(Schema::hasColumn('eval_datasets', $column))->toBeTrue("missing column {$column}");
    }
});

it('creates the eval_runs table with expected columns', function (): void {
    expect(Schema::hasTable('eval_runs'))->toBeTrue();

    $expected = [
        'id', 'dataset_id', 'dataset_name', 'suite_class', 'subject_type', 'subject_class',
        'commit_sha', 'model',
        'passed', 'pass_count', 'fail_count', 'error_count', 'total_count',
        'duration_ms', 'total_cost_usd', 'total_tokens_in', 'total_tokens_out',
        'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('eval_runs', $column))->toBeTrue("missing column {$column}");
    }
});

it('creates the eval_results table with expected columns', function (): void {
    expect(Schema::hasTable('eval_results'))->toBeTrue();

    $expected = [
        'id', 'run_id', 'case_index', 'case_name', 'input', 'output', 'expected',
        'passed', 'assertion_results', 'error_class', 'error_message', 'error_trace',
        'duration_ms', 'latency_ms', 'tokens_in', 'tokens_out', 'cost_usd', 'model',
        'created_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('eval_results', $column))->toBeTrue("missing column {$column}");
    }
});

it('creates a foreign key from eval_runs to eval_datasets with cascade', function (): void {
    $datasetId = (string) Str::ulid();
    DB::table('eval_datasets')->insert([
        'id' => $datasetId,
        'name' => 'fk-test',
        'case_count' => 1,
        'checksum' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $runId = (string) Str::ulid();
    DB::table('eval_runs')->insert([
        'id' => $runId,
        'dataset_id' => $datasetId,
        'dataset_name' => 'fk-test',
        'suite_class' => null,
        'subject_type' => 'callable',
        'subject_class' => null,
        'commit_sha' => null,
        'model' => null,
        'passed' => true,
        'pass_count' => 1,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 1,
        'duration_ms' => 1.0,
        'total_cost_usd' => null,
        'total_tokens_in' => null,
        'total_tokens_out' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('eval_runs')->count())->toBe(1);

    DB::table('eval_datasets')->where('id', $datasetId)->delete();

    expect(DB::table('eval_runs')->count())->toBe(0);
});

it('creates a foreign key from eval_results to eval_runs with cascade', function (): void {
    $datasetId = (string) Str::ulid();
    DB::table('eval_datasets')->insert([
        'id' => $datasetId,
        'name' => 'fk-test-2',
        'case_count' => 1,
        'checksum' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $runId = (string) Str::ulid();
    DB::table('eval_runs')->insert([
        'id' => $runId,
        'dataset_id' => $datasetId,
        'dataset_name' => 'fk-test-2',
        'suite_class' => null,
        'subject_type' => 'callable',
        'subject_class' => null,
        'commit_sha' => null,
        'model' => null,
        'passed' => true,
        'pass_count' => 1,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 1,
        'duration_ms' => 1.0,
        'total_cost_usd' => null,
        'total_tokens_in' => null,
        'total_tokens_out' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $resultId = (string) Str::ulid();
    DB::table('eval_results')->insert([
        'id' => $resultId,
        'run_id' => $runId,
        'case_index' => 0,
        'case_name' => null,
        'input' => json_encode(['foo' => 'bar']),
        'output' => null,
        'expected' => null,
        'passed' => true,
        'assertion_results' => json_encode([]),
        'error_class' => null,
        'error_message' => null,
        'error_trace' => null,
        'duration_ms' => 1.0,
        'latency_ms' => null,
        'tokens_in' => null,
        'tokens_out' => null,
        'cost_usd' => null,
        'model' => null,
        'created_at' => now(),
    ]);

    expect(DB::table('eval_results')->count())->toBe(1);

    DB::table('eval_runs')->where('id', $runId)->delete();

    expect(DB::table('eval_results')->count())->toBe(0);
});

it('creates the expected indexes on eval_runs and eval_results', function (): void {
    $runsIndexes = collect(DB::select("PRAGMA index_list('eval_runs')"))
        ->pluck('name')
        ->all();

    $hasCreatedAtIndex = collect($runsIndexes)->contains(
        fn (string $name): bool => str_contains($name, 'created_at')
    );
    $hasDatasetIdIndex = collect($runsIndexes)->contains(
        fn (string $name): bool => str_contains($name, 'dataset_id')
    );
    $hasPassedCreatedIndex = collect($runsIndexes)->contains(
        fn (string $name): bool => str_contains($name, 'passed') && str_contains($name, 'created_at')
    );

    expect($hasCreatedAtIndex)->toBeTrue('missing created_at index on eval_runs')
        ->and($hasDatasetIdIndex)->toBeTrue('missing dataset_id index on eval_runs')
        ->and($hasPassedCreatedIndex)->toBeTrue('missing passed+created_at index on eval_runs');

    $resultsIndexes = collect(DB::select("PRAGMA index_list('eval_results')"))
        ->pluck('name')
        ->all();

    $hasRunCaseIndex = collect($resultsIndexes)->contains(
        fn (string $name): bool => str_contains($name, 'run_id') && str_contains($name, 'case_index')
    );

    expect($hasRunCaseIndex)->toBeTrue('missing run_id+case_index index on eval_results');
});

it('creates the shadow_captures table with expected columns', function (): void {
    expect(Schema::hasTable('shadow_captures'))->toBeTrue();

    $expected = [
        'id', 'agent_class', 'prompt_hash', 'input_payload', 'output',
        'tokens_in', 'tokens_out', 'cost_usd', 'latency_ms', 'model_used',
        'captured_at', 'sample_rate', 'is_anonymized',
        'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('shadow_captures', $column))->toBeTrue("missing column {$column}");
    }
});

it('creates the expected indexes on shadow_captures', function (): void {
    $indexes = collect(DB::select("PRAGMA index_list('shadow_captures')"))
        ->pluck('name')
        ->all();

    $hasAgentClassIndex = collect($indexes)->contains(
        fn (string $name): bool => str_contains($name, 'agent_class') && ! str_contains($name, 'captured_at')
    );
    $hasCapturedAtIndex = collect($indexes)->contains(
        fn (string $name): bool => str_contains($name, 'captured_at') && ! str_contains($name, 'agent_class')
    );
    $hasAgentCapturedIndex = collect($indexes)->contains(
        fn (string $name): bool => str_contains($name, 'agent_class') && str_contains($name, 'captured_at')
    );
    $hasPromptHashIndex = collect($indexes)->contains(
        fn (string $name): bool => str_contains($name, 'prompt_hash')
    );

    expect($hasAgentClassIndex)->toBeTrue('missing agent_class index on shadow_captures')
        ->and($hasCapturedAtIndex)->toBeTrue('missing captured_at index on shadow_captures')
        ->and($hasAgentCapturedIndex)->toBeTrue('missing agent_class+captured_at index on shadow_captures')
        ->and($hasPromptHashIndex)->toBeTrue('missing prompt_hash index on shadow_captures');
});

it('rolls back shadow_captures cleanly', function (): void {
    expect(Schema::hasTable('shadow_captures'))->toBeTrue();

    $base = __DIR__.'/../../../database/migrations';
    $migration = require $base.'/2026_04_17_000001_create_shadow_captures_table.php';

    $migration->down();

    expect(Schema::hasTable('shadow_captures'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('shadow_captures'))->toBeTrue();
});

it('creates the shadow_evals table with expected columns', function (): void {
    expect(Schema::hasTable('shadow_evals'))->toBeTrue();

    $expected = [
        'id', 'capture_id', 'agent_class', 'passed',
        'total_assertions', 'passed_assertions', 'failed_assertions',
        'assertion_results',
        'judge_cost_usd', 'judge_tokens_in', 'judge_tokens_out',
        'evaluation_duration_ms', 'evaluated_at',
        'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('shadow_evals', $column))->toBeTrue("missing column {$column}");
    }
});

it('creates a foreign key from shadow_evals to shadow_captures with cascade', function (): void {
    $captureId = (string) Str::ulid();
    DB::table('shadow_captures')->insert([
        'id' => $captureId,
        'agent_class' => 'App\\Agents\\FK',
        'prompt_hash' => str_repeat('f', 64),
        'input_payload' => json_encode(['prompt' => 'hello']),
        'output' => 'hi',
        'tokens_in' => null,
        'tokens_out' => null,
        'cost_usd' => null,
        'latency_ms' => null,
        'model_used' => null,
        'captured_at' => now(),
        'sample_rate' => 1.0,
        'is_anonymized' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $evalId = (string) Str::ulid();
    DB::table('shadow_evals')->insert([
        'id' => $evalId,
        'capture_id' => $captureId,
        'agent_class' => 'App\\Agents\\FK',
        'passed' => true,
        'total_assertions' => 1,
        'passed_assertions' => 1,
        'failed_assertions' => 0,
        'assertion_results' => json_encode([['name' => 'contains', 'passed' => true]]),
        'judge_cost_usd' => null,
        'judge_tokens_in' => null,
        'judge_tokens_out' => null,
        'evaluation_duration_ms' => 12.5,
        'evaluated_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('shadow_evals')->count())->toBe(1);

    DB::table('shadow_captures')->where('id', $captureId)->delete();

    expect(DB::table('shadow_evals')->count())->toBe(0);
});

it('creates the expected indexes on shadow_evals', function (): void {
    $indexes = collect(DB::select("PRAGMA index_list('shadow_evals')"))
        ->pluck('name')
        ->all();

    $hasCaptureIdIndex = collect($indexes)->contains(
        fn (string $name): bool => str_contains($name, 'capture_id')
    );
    $hasAgentClassIndex = collect($indexes)->contains(
        fn (string $name): bool => str_contains($name, 'agent_class') && ! str_contains($name, 'evaluated_at')
    );
    $hasAgentEvaluatedIndex = collect($indexes)->contains(
        fn (string $name): bool => str_contains($name, 'agent_class') && str_contains($name, 'evaluated_at')
    );
    $hasPassedEvaluatedIndex = collect($indexes)->contains(
        fn (string $name): bool => str_contains($name, 'passed') && str_contains($name, 'evaluated_at')
    );

    expect($hasCaptureIdIndex)->toBeTrue('missing capture_id index on shadow_evals')
        ->and($hasAgentClassIndex)->toBeTrue('missing agent_class index on shadow_evals')
        ->and($hasAgentEvaluatedIndex)->toBeTrue('missing agent_class+evaluated_at index on shadow_evals')
        ->and($hasPassedEvaluatedIndex)->toBeTrue('missing passed+evaluated_at index on shadow_evals');
});

it('rolls back shadow_evals cleanly', function (): void {
    expect(Schema::hasTable('shadow_evals'))->toBeTrue();

    $base = __DIR__.'/../../../database/migrations';
    $migration = require $base.'/2026_04_17_000002_create_shadow_evals_table.php';

    $migration->down();

    expect(Schema::hasTable('shadow_evals'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('shadow_evals'))->toBeTrue();
});

it('provides down() migrations that drop all three tables', function (): void {
    expect(Schema::hasTable('eval_datasets'))->toBeTrue()
        ->and(Schema::hasTable('eval_runs'))->toBeTrue()
        ->and(Schema::hasTable('eval_results'))->toBeTrue();

    $base = __DIR__.'/../../../database/migrations';
    $results = require $base.'/create_eval_results_table.php';
    $runs = require $base.'/create_eval_runs_table.php';
    $datasets = require $base.'/create_eval_datasets_table.php';

    $results->down();
    $runs->down();
    $datasets->down();

    expect(Schema::hasTable('eval_datasets'))->toBeFalse()
        ->and(Schema::hasTable('eval_runs'))->toBeFalse()
        ->and(Schema::hasTable('eval_results'))->toBeFalse();

    $datasets->up();
    $runs->up();
    $results->up();

    expect(Schema::hasTable('eval_datasets'))->toBeTrue()
        ->and(Schema::hasTable('eval_runs'))->toBeTrue()
        ->and(Schema::hasTable('eval_results'))->toBeTrue();
});
