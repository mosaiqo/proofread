<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Mosaiqo\Proofread\Diff\EvalRunDiff;
use Mosaiqo\Proofread\Events\EvalRunPersisted;
use Mosaiqo\Proofread\Events\EvalRunRegressed;
use Mosaiqo\Proofread\Listeners\CheckForRegressionListener;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * @param  list<array<string, mixed>>  $resultsData
 */
function seedListenerRun(string $datasetName, array $resultsData): EvalRun
{
    $dataset = EvalDataset::query()->firstOrCreate(
        ['name' => $datasetName],
        ['case_count' => count($resultsData), 'checksum' => hash('sha256', $datasetName)],
    );

    $passCount = 0;
    $failCount = 0;
    foreach ($resultsData as $row) {
        if (($row['passed'] ?? true) === true) {
            $passCount++;
        } else {
            $failCount++;
        }
    }

    $run = new EvalRun;
    $run->fill([
        'dataset_id' => $dataset->id,
        'dataset_name' => $datasetName,
        'suite_class' => null,
        'subject_type' => 'unknown',
        'subject_class' => null,
        'commit_sha' => null,
        'model' => null,
        'passed' => $failCount === 0,
        'pass_count' => $passCount,
        'fail_count' => $failCount,
        'error_count' => 0,
        'total_count' => count($resultsData),
        'duration_ms' => 10.0,
        'total_cost_usd' => null,
        'total_tokens_in' => null,
        'total_tokens_out' => null,
    ]);
    $run->save();

    foreach ($resultsData as $row) {
        $result = new EvalResult;
        $result->fill([
            'run_id' => $run->id,
            'case_index' => $row['case_index'],
            'case_name' => null,
            'input' => ['value' => 'x'],
            'output' => null,
            'expected' => null,
            'passed' => $row['passed'] ?? true,
            'assertion_results' => $row['assertion_results'] ?? [],
            'error_class' => null,
            'error_message' => null,
            'error_trace' => null,
            'duration_ms' => 1.0,
            'latency_ms' => null,
            'tokens_in' => null,
            'tokens_out' => null,
            'cost_usd' => null,
            'model' => null,
        ]);
        $result->save();
    }

    return $run->fresh(['results']) ?? $run;
}

it('dispatches EvalRunRegressed when regression is detected', function (): void {
    $base = seedListenerRun('ds-listener', [
        ['case_index' => 0, 'passed' => true],
    ]);
    // Ensure distinct created_at ordering.
    $base->created_at = now()->subMinute();
    $base->save();

    $head = seedListenerRun('ds-listener', [
        ['case_index' => 0, 'passed' => false],
    ]);

    Event::fake([EvalRunRegressed::class]);

    $listener = new CheckForRegressionListener(new EvalRunDiff);
    $listener->handle(new EvalRunPersisted($head));

    Event::assertDispatched(
        EvalRunRegressed::class,
        function (EvalRunRegressed $event) use ($base, $head): bool {
            return $event->baseRun->is($base)
                && $event->headRun->is($head)
                && $event->delta->hasRegressions();
        },
    );
});

it('does not dispatch when there is no previous run', function (): void {
    $head = seedListenerRun('ds-first', [
        ['case_index' => 0, 'passed' => false],
    ]);

    Event::fake([EvalRunRegressed::class]);

    $listener = new CheckForRegressionListener(new EvalRunDiff);
    $listener->handle(new EvalRunPersisted($head));

    Event::assertNotDispatched(EvalRunRegressed::class);
});

it('does not dispatch when no regression', function (): void {
    $base = seedListenerRun('ds-improve', [
        ['case_index' => 0, 'passed' => false],
    ]);
    $base->created_at = now()->subMinute();
    $base->save();

    $head = seedListenerRun('ds-improve', [
        ['case_index' => 0, 'passed' => true],
    ]);

    Event::fake([EvalRunRegressed::class]);

    $listener = new CheckForRegressionListener(new EvalRunDiff);
    $listener->handle(new EvalRunPersisted($head));

    Event::assertNotDispatched(EvalRunRegressed::class);
});

it('ignores runs of different datasets', function (): void {
    $other = seedListenerRun('ds-other', [
        ['case_index' => 0, 'passed' => true],
    ]);
    $other->created_at = now()->subMinute();
    $other->save();

    $head = seedListenerRun('ds-target', [
        ['case_index' => 0, 'passed' => false],
    ]);

    Event::fake([EvalRunRegressed::class]);

    $listener = new CheckForRegressionListener(new EvalRunDiff);
    $listener->handle(new EvalRunPersisted($head));

    // No previous run of ds-target exists, so no regression event.
    Event::assertNotDispatched(EvalRunRegressed::class);
});
