<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Bus;
use Mosaiqo\Proofread\Jobs\RunEvalSuiteJob;
use Mosaiqo\Proofread\Models\EvalRun as EvalRunModel;
use Mosaiqo\Proofread\Runner\EvalPersister;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\PassingSuite;

it('dispatches to the configured queue connection and queue', function (): void {
    /** @var Repository $config */
    $config = app('config');
    $config->set('proofread.queue.connection', 'sync');
    $config->set('proofread.queue.eval_queue', 'evals');

    Bus::fake();

    RunEvalSuiteJob::dispatch(PassingSuite::class);

    Bus::assertDispatched(
        RunEvalSuiteJob::class,
        fn (RunEvalSuiteJob $job): bool => $job->queue === 'evals'
            && $job->connection === 'sync'
            && $job->suiteClass === PassingSuite::class,
    );
});

it('runs the suite and persists the result when handled', function (): void {
    $job = new RunEvalSuiteJob(PassingSuite::class);

    $job->handle(app(EvalRunner::class), app(EvalPersister::class));

    expect(EvalRunModel::query()->count())->toBe(1);
});

it('sets the suite class on the persisted run', function (): void {
    $job = new RunEvalSuiteJob(PassingSuite::class);

    $job->handle(app(EvalRunner::class), app(EvalPersister::class));

    $run = EvalRunModel::query()->firstOrFail();
    expect($run->suite_class)->toBe(PassingSuite::class);
});

it('sets the commit sha on the persisted run when provided', function (): void {
    $job = new RunEvalSuiteJob(PassingSuite::class, commitSha: 'abc123');

    $job->handle(app(EvalRunner::class), app(EvalPersister::class));

    $run = EvalRunModel::query()->firstOrFail();
    expect($run->commit_sha)->toBe('abc123');
});

it('does not persist when persist is false', function (): void {
    $job = new RunEvalSuiteJob(PassingSuite::class, persist: false);

    $job->handle(app(EvalRunner::class), app(EvalPersister::class));

    expect(EvalRunModel::query()->count())->toBe(0);
});

it('fails when the suite class does not exist', function (): void {
    $job = new RunEvalSuiteJob('Nonexistent\\Suite\\Class');

    expect(fn () => $job->handle(app(EvalRunner::class), app(EvalPersister::class)))
        ->toThrow(InvalidArgumentException::class, 'Nonexistent\\Suite\\Class');
});

it('fails when the class does not extend EvalSuite', function (): void {
    $job = new RunEvalSuiteJob(DateTime::class);

    expect(fn () => $job->handle(app(EvalRunner::class), app(EvalPersister::class)))
        ->toThrow(InvalidArgumentException::class, DateTime::class);
});

it('honors tries = 1 by default', function (): void {
    $job = new RunEvalSuiteJob(PassingSuite::class);

    expect($job->tries)->toBe(1);
});

it('honors the configured timeout', function (): void {
    /** @var Repository $config */
    $config = app('config');
    $config->set('proofread.queue.timeout', 900);

    $job = new RunEvalSuiteJob(PassingSuite::class);

    expect($job->timeout)->toBe(900);
});
