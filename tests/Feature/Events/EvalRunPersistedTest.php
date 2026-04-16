<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Mosaiqo\Proofread\Events\EvalRunPersisted;
use Mosaiqo\Proofread\Runner\EvalPersister;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;

function makePersistedPassingResult(): EvalResult
{
    return EvalResult::make(
        ['input' => 'hi'],
        'hello',
        [AssertionResult::pass('ok', null, ['assertion_name' => 'contains'])],
        5.0,
    );
}

function makePersistedRun(): EvalRun
{
    return EvalRun::make(
        Dataset::make('events-test', [['input' => 'hi']]),
        [makePersistedPassingResult()],
        10.0,
    );
}

it('dispatches EvalRunPersisted after persist', function (): void {
    Event::fake([EvalRunPersisted::class]);

    $model = (new EvalPersister)->persist(makePersistedRun());

    Event::assertDispatched(
        EvalRunPersisted::class,
        fn (EvalRunPersisted $event): bool => $event->run->is($model),
    );
});

it('dispatches EvalRunPersisted with a persisted EvalRun model', function (): void {
    Event::fake([EvalRunPersisted::class]);

    (new EvalPersister)->persist(makePersistedRun());

    Event::assertDispatched(
        EvalRunPersisted::class,
        fn (EvalRunPersisted $event): bool => $event->run->exists
            && $event->run->dataset_name === 'events-test',
    );
});
