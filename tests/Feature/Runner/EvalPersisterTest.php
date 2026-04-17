<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Models\EvalComparison;
use Mosaiqo\Proofread\Models\EvalDataset as EvalDatasetModel;
use Mosaiqo\Proofread\Models\EvalDatasetVersion as EvalDatasetVersionModel;
use Mosaiqo\Proofread\Models\EvalResult as EvalResultModel;
use Mosaiqo\Proofread\Models\EvalRun as EvalRunModel;
use Mosaiqo\Proofread\Runner\EvalPersister;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;

/**
 * @param  array<int, array<string, mixed>>  $cases
 * @param  list<EvalResult>  $results
 */
function buildRun(array $cases, array $results, float $duration = 10.0): EvalRun
{
    return EvalRun::make(Dataset::make('persist-test', $cases), $results, $duration);
}

function persisterPassingResult(string $input = 'hi', string $output = 'hello', ?string $name = null): EvalResult
{
    $case = ['input' => $input];
    if ($name !== null) {
        $case['meta'] = ['name' => $name];
    }

    return EvalResult::make(
        $case,
        $output,
        [
            AssertionResult::pass('ok', null, ['assertion_name' => 'contains']),
        ],
        5.0,
    );
}

function persisterFailingResult(): EvalResult
{
    return EvalResult::make(
        ['input' => 'foo'],
        'bar',
        [
            AssertionResult::fail('missing needle', null, ['assertion_name' => 'contains']),
        ],
        7.0,
    );
}

it('persists a new dataset on first run', function (): void {
    $run = buildRun([['input' => 'hi']], [persisterPassingResult()]);

    $persister = new EvalPersister;
    $persister->persist($run);

    expect(EvalDatasetModel::query()->count())->toBe(1);
    $dataset = EvalDatasetModel::query()->firstOrFail();
    expect($dataset->name)->toBe('persist-test')
        ->and($dataset->case_count)->toBe(1)
        ->and($dataset->checksum)->not->toBeNull();
});

it('reuses an existing dataset by name', function (): void {
    $run = buildRun([['input' => 'hi']], [persisterPassingResult()]);
    $persister = new EvalPersister;

    $persister->persist($run);
    $persister->persist($run);

    expect(EvalDatasetModel::query()->count())->toBe(1)
        ->and(EvalRunModel::query()->count())->toBe(2);
});

it('updates the dataset checksum when cases change', function (): void {
    $persister = new EvalPersister;

    $first = buildRun([['input' => 'hi']], [persisterPassingResult()]);
    $persister->persist($first);
    $firstChecksum = EvalDatasetModel::query()->firstOrFail()->checksum;

    $second = buildRun([['input' => 'hi'], ['input' => 'bye']], [persisterPassingResult(), persisterPassingResult('bye', 'later')]);
    $persister->persist($second);

    $dataset = EvalDatasetModel::query()->firstOrFail();
    expect($dataset->checksum)->not->toBe($firstChecksum)
        ->and($dataset->case_count)->toBe(2);
});

it('persists a run with aggregate counts', function (): void {
    $run = buildRun(
        [['input' => 'a'], ['input' => 'b'], ['input' => 'c']],
        [persisterPassingResult(), persisterFailingResult(), persisterPassingResult()],
        42.0,
    );

    (new EvalPersister)->persist($run);

    $model = EvalRunModel::query()->firstOrFail();
    expect($model->total_count)->toBe(3)
        ->and($model->pass_count)->toBe(2)
        ->and($model->fail_count)->toBe(1)
        ->and($model->error_count)->toBe(0)
        ->and($model->passed)->toBeFalse()
        ->and($model->duration_ms)->toBe(42.0)
        ->and($model->dataset_name)->toBe('persist-test');
});

it('persists all results of a run', function (): void {
    $run = buildRun(
        [['input' => 'a'], ['input' => 'b', 'meta' => ['name' => 'bee']]],
        [persisterPassingResult('a', 'aa'), persisterPassingResult('b', 'bb', 'bee')],
    );

    (new EvalPersister)->persist($run);

    expect(EvalResultModel::query()->count())->toBe(2);
    $resultModels = EvalResultModel::query()->orderBy('case_index')->get();
    expect($resultModels[0]->case_index)->toBe(0)
        ->and($resultModels[0]->case_name)->toBeNull()
        ->and($resultModels[0]->output)->toBe('aa')
        ->and($resultModels[1]->case_name)->toBe('bee')
        ->and($resultModels[1]->output)->toBe('bb');
});

it('serializes assertion results as structured JSON', function (): void {
    $result = EvalResult::make(
        ['input' => 'x'],
        'y',
        [
            AssertionResult::pass('ok', 0.9, [
                'assertion_name' => 'contains',
                'extra' => 'meta',
            ]),
            AssertionResult::fail('nope', 0.2, ['assertion_name' => 'similar']),
        ],
        5.0,
    );

    $run = buildRun([['input' => 'x']], [$result]);
    (new EvalPersister)->persist($run);

    $stored = EvalResultModel::query()->firstOrFail();
    $assertions = $stored->assertion_results;

    expect($assertions)->toHaveCount(2)
        ->and($assertions[0])->toMatchArray([
            'name' => 'contains',
            'passed' => true,
            'reason' => 'ok',
            'score' => 0.9,
        ])
        ->and($assertions[0]['metadata'])->toBeArray()
        ->and($assertions[1]['name'])->toBe('similar')
        ->and($assertions[1]['passed'])->toBeFalse()
        ->and($assertions[1]['score'])->toBe(0.2);
});

it('persists error information when a case threw', function (): void {
    $exception = new RuntimeException('boom');
    $erroring = EvalResult::make(['input' => 'x'], null, [], 4.0, $exception);

    $run = buildRun([['input' => 'x']], [$erroring]);
    (new EvalPersister)->persist($run);

    $stored = EvalResultModel::query()->firstOrFail();
    expect($stored->error_class)->toBe(RuntimeException::class)
        ->and($stored->error_message)->toBe('boom')
        ->and($stored->error_trace)->toBeString()
        ->and($stored->error_trace)->not->toBeEmpty()
        ->and($stored->passed)->toBeFalse();

    $runModel = EvalRunModel::query()->firstOrFail();
    expect($runModel->error_count)->toBe(1)
        ->and($runModel->fail_count)->toBe(0);
});

it('extracts latency, tokens, cost and model from assertion metadata', function (): void {
    $result = EvalResult::make(
        ['input' => 'x'],
        'y',
        [
            AssertionResult::pass('ok', null, [
                'assertion_name' => 'contains',
                'latency_ms' => 123.5,
                'tokens_in' => 500,
                'tokens_out' => 250,
                'cost_usd' => 0.0042,
                'model' => 'claude-haiku-4-5',
            ]),
        ],
        5.0,
    );

    $run = buildRun([['input' => 'x']], [$result]);
    (new EvalPersister)->persist($run);

    $stored = EvalResultModel::query()->firstOrFail();
    expect($stored->latency_ms)->toBe(123.5)
        ->and($stored->tokens_in)->toBe(500)
        ->and($stored->tokens_out)->toBe(250)
        ->and($stored->cost_usd)->toBe(0.0042)
        ->and($stored->model)->toBe('claude-haiku-4-5');

    $runModel = EvalRunModel::query()->firstOrFail();
    expect($runModel->total_tokens_in)->toBe(500)
        ->and($runModel->total_tokens_out)->toBe(250)
        ->and($runModel->total_cost_usd)->toBe(0.0042)
        ->and($runModel->model)->toBe('claude-haiku-4-5');
});

it('wraps everything in a transaction and rolls back on failure', function (): void {
    $run = buildRun([['input' => 'a']], [persisterPassingResult()]);

    // Force a failure by pre-seeding a dataset with the same name but then
    // crashing during result creation via a reserved connection mock.
    // Simpler: forge a persister that throws after inserting the run.

    $persister = new class extends EvalPersister
    {
        protected function buildResultAttributes(
            string $runId,
            int $index,
            EvalResult $result,
        ): array {
            throw new RuntimeException('synthetic failure');
        }
    };

    $caught = null;
    try {
        $persister->persist($run);
    } catch (RuntimeException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(RuntimeException::class)
        ->and($caught?->getMessage())->toBe('synthetic failure');

    expect(EvalDatasetModel::query()->count())->toBe(0)
        ->and(EvalRunModel::query()->count())->toBe(0)
        ->and(EvalResultModel::query()->count())->toBe(0);
});

it('accepts optional suite class and commit sha', function (): void {
    $run = buildRun([['input' => 'x']], [persisterPassingResult()]);

    (new EvalPersister)->persist(
        $run,
        suiteClass: 'App\\Suites\\Foo',
        commitSha: 'abc1234',
    );

    $model = EvalRunModel::query()->firstOrFail();
    expect($model->suite_class)->toBe('App\\Suites\\Foo')
        ->and($model->commit_sha)->toBe('abc1234');
});

it('accepts optional subject type and class', function (): void {
    $run = buildRun([['input' => 'x']], [persisterPassingResult()]);

    (new EvalPersister)->persist(
        $run,
        subjectType: 'agent',
        subjectClass: 'App\\Agents\\Bar',
    );

    $model = EvalRunModel::query()->firstOrFail();
    expect($model->subject_type)->toBe('agent')
        ->and($model->subject_class)->toBe('App\\Agents\\Bar');
});

it('defaults subject_type to unknown when not provided', function (): void {
    $run = buildRun([['input' => 'x']], [persisterPassingResult()]);

    (new EvalPersister)->persist($run);

    $model = EvalRunModel::query()->firstOrFail();
    expect($model->subject_type)->toBe('unknown')
        ->and($model->subject_class)->toBeNull();
});

it('returns the persisted run model', function (): void {
    $run = buildRun([['input' => 'x']], [persisterPassingResult()]);

    $model = (new EvalPersister)->persist($run);

    expect($model)->toBeInstanceOf(EvalRunModel::class)
        ->and($model->id)->toBeString()
        ->and(strlen($model->id))->toBe(26);
});

it('creates a dataset version on first persist', function (): void {
    $run = buildRun([['input' => 'hi']], [persisterPassingResult()]);

    (new EvalPersister)->persist($run);

    expect(EvalDatasetVersionModel::query()->count())->toBe(1);
    $version = EvalDatasetVersionModel::query()->firstOrFail();
    $dataset = EvalDatasetModel::query()->firstOrFail();
    expect($version->eval_dataset_id)->toBe($dataset->id)
        ->and($version->checksum)->toBe($dataset->checksum)
        ->and($version->case_count)->toBe(1);
});

it('reuses an existing version when the dataset has not changed', function (): void {
    $persister = new EvalPersister;
    $run = buildRun([['input' => 'hi']], [persisterPassingResult()]);

    $persister->persist($run);
    $persister->persist($run);

    expect(EvalDatasetVersionModel::query()->count())->toBe(1)
        ->and(EvalRunModel::query()->count())->toBe(2);

    $version = EvalDatasetVersionModel::query()->firstOrFail();
    expect($version->runs()->count())->toBe(2);
});

it('creates a new version when the dataset cases change', function (): void {
    $persister = new EvalPersister;

    $first = buildRun([['input' => 'hi']], [persisterPassingResult()]);
    $persister->persist($first);

    $second = buildRun(
        [['input' => 'hi'], ['input' => 'bye']],
        [persisterPassingResult(), persisterPassingResult('bye', 'later')],
    );
    $persister->persist($second);

    expect(EvalDatasetVersionModel::query()->count())->toBe(2);
});

it('links the run to the dataset version', function (): void {
    $run = buildRun([['input' => 'hi']], [persisterPassingResult()]);

    $runModel = (new EvalPersister)->persist($run);

    $runModel->refresh();
    expect($runModel->dataset_version_id)->not->toBeNull()
        ->and($runModel->datasetVersion)->not->toBeNull()
        ->and($runModel->datasetVersion?->checksum)->toBe($runModel->dataset?->checksum);
});

it('stores the cases snapshot on the version', function (): void {
    $cases = [
        ['input' => 'a', 'expected' => ['ok' => true]],
        ['input' => 'b', 'meta' => ['name' => 'bee']],
    ];
    $run = buildRun($cases, [persisterPassingResult('a', 'aa'), persisterPassingResult('b', 'bb', 'bee')]);

    (new EvalPersister)->persist($run);

    $version = EvalDatasetVersionModel::query()->firstOrFail();
    expect($version->cases)->toBe($cases)
        ->and($version->case_count)->toBe(2);
});

it('updates first_seen_at to the first time a version appeared', function (): void {
    $persister = new EvalPersister;
    $run = buildRun([['input' => 'hi']], [persisterPassingResult()]);

    $persister->persist($run);
    $firstSeenAt = EvalDatasetVersionModel::query()->firstOrFail()->first_seen_at;

    $persister->persist($run);

    $version = EvalDatasetVersionModel::query()->firstOrFail();
    expect($version->first_seen_at->toIso8601String())->toBe($firstSeenAt->toIso8601String());
});

it('stores comparison_id and subject_label when provided', function (): void {
    $comparison = EvalComparison::query()->create([
        'name' => 'cmp',
        'dataset_name' => 'persist-test',
        'subject_labels' => ['haiku'],
        'total_runs' => 1,
        'passed_runs' => 0,
        'failed_runs' => 0,
        'duration_ms' => 10.0,
    ]);

    $run = buildRun([['input' => 'x']], [persisterPassingResult()]);

    (new EvalPersister)->persist(
        $run,
        comparisonId: $comparison->id,
        subjectLabel: 'haiku',
    );

    $model = EvalRunModel::query()->firstOrFail();
    expect($model->comparison_id)->toBe($comparison->id)
        ->and($model->subject_label)->toBe('haiku');
});

it('leaves comparison fields null when not provided', function (): void {
    $run = buildRun([['input' => 'x']], [persisterPassingResult()]);

    (new EvalPersister)->persist($run);

    $model = EvalRunModel::query()->firstOrFail();
    expect($model->comparison_id)->toBeNull()
        ->and($model->subject_label)->toBeNull();
});
