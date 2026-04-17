<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\PulseServiceProvider;
use Laravel\Pulse\Value;
use Mosaiqo\Proofread\Events\EvalRunPersisted;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalRun;
use Mosaiqo\Proofread\ProofreadServiceProvider;

beforeEach(function (): void {
    /** @var Application $app */
    $app = app();
    $app->register(PulseServiceProvider::class);
    $app->register(new ProofreadServiceProvider($app), force: true);
    Pulse::startRecording();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function seedPulseRun(array $overrides = []): EvalRun
{
    $dataset = EvalDataset::query()->firstOrCreate(
        ['name' => $overrides['dataset_name'] ?? 'pulse-ds'],
        ['case_count' => 1, 'checksum' => hash('sha256', (string) ($overrides['dataset_name'] ?? 'pulse-ds'))],
    );

    $run = new EvalRun;
    $run->fill(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => 'pulse-ds',
        'suite_class' => 'App\\Evals\\ExampleSuite',
        'subject_type' => 'agent',
        'subject_class' => 'App\\Agents\\ExampleAgent',
        'commit_sha' => 'abc1234',
        'model' => 'claude-haiku-4-5',
        'passed' => true,
        'pass_count' => 3,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 3,
        'duration_ms' => 1234.5,
        'total_cost_usd' => 0.0123,
        'total_tokens_in' => 500,
        'total_tokens_out' => 120,
    ], $overrides));
    $run->save();

    return $run;
}

/**
 * @return list<Entry>
 */
function capturePulseEntries(callable $callback): array
{
    /** @var list<Entry|Value> $captured */
    $captured = [];
    Pulse::filter(function ($entry) use (&$captured): bool {
        $captured[] = $entry;

        return false;
    });

    $callback();

    Pulse::ingest();

    return array_values(array_filter(
        $captured,
        static fn ($entry): bool => $entry instanceof Entry,
    ));
}

it('records a pulse entry when an eval run is persisted', function (): void {
    $run = seedPulseRun();

    $entries = capturePulseEntries(function () use ($run): void {
        Event::dispatch(new EvalRunPersisted($run));
    });

    expect($entries)->not->toBeEmpty();
    $types = array_map(fn (Entry $e) => $e->type, $entries);
    expect($types)->toContain('proofread_eval');
});

it('records pass and fail status separately by key suffix', function (): void {
    $passed = seedPulseRun(['dataset_name' => 'pulse-pass', 'passed' => true]);
    $failed = seedPulseRun(['dataset_name' => 'pulse-fail', 'passed' => false]);

    $entries = capturePulseEntries(function () use ($passed, $failed): void {
        Event::dispatch(new EvalRunPersisted($passed));
        Event::dispatch(new EvalRunPersisted($failed));
    });

    $statusEntries = array_filter($entries, fn (Entry $e) => $e->type === 'proofread_eval');
    $keys = array_map(fn (Entry $e) => $e->key, $statusEntries);
    expect($keys)->toContain('pulse-pass::passed');
    expect($keys)->toContain('pulse-fail::failed');
});

it('records total cost in micro-dollars when available', function (): void {
    $run = seedPulseRun(['dataset_name' => 'pulse-cost', 'total_cost_usd' => 0.0123]);

    $entries = capturePulseEntries(function () use ($run): void {
        Event::dispatch(new EvalRunPersisted($run));
    });

    $costEntries = array_values(array_filter($entries, fn (Entry $e) => $e->type === 'proofread_eval_cost'));
    expect($costEntries)->toHaveCount(1);
    expect($costEntries[0]->value)->toBe(12_300);
    expect($costEntries[0]->key)->toBe('pulse-cost');
    expect($costEntries[0]->aggregations())->toContain('sum');
});

it('skips cost recording when total_cost_usd is null', function (): void {
    $run = seedPulseRun(['dataset_name' => 'pulse-nocost', 'total_cost_usd' => null]);

    $entries = capturePulseEntries(function () use ($run): void {
        Event::dispatch(new EvalRunPersisted($run));
    });

    $costEntries = array_filter($entries, fn (Entry $e) => $e->type === 'proofread_eval_cost');
    expect($costEntries)->toBeEmpty();
});

it('records duration with avg and max aggregations', function (): void {
    $run = seedPulseRun(['dataset_name' => 'pulse-duration', 'duration_ms' => 2500.75]);

    $entries = capturePulseEntries(function () use ($run): void {
        Event::dispatch(new EvalRunPersisted($run));
    });

    $durationEntries = array_values(array_filter($entries, fn (Entry $e) => $e->type === 'proofread_eval_duration'));
    expect($durationEntries)->toHaveCount(1);
    expect($durationEntries[0]->value)->toBe(2501);
    expect($durationEntries[0]->key)->toBe('pulse-duration');
    expect($durationEntries[0]->aggregations())
        ->toContain('avg')
        ->toContain('max');
});

it('does not record when pulse is paused', function (): void {
    Pulse::stopRecording();

    $run = seedPulseRun(['dataset_name' => 'pulse-paused']);

    $entries = capturePulseEntries(function () use ($run): void {
        Event::dispatch(new EvalRunPersisted($run));
    });

    $proofreadEntries = array_filter(
        $entries,
        fn (Entry $e) => str_starts_with($e->type, 'proofread_eval'),
    );
    expect($proofreadEntries)->toBeEmpty();
});

it('publishes the pulse card stub under vendor/pulse/cards', function (): void {
    $target = resource_path('views/vendor/pulse/cards/proofread.blade.php');
    if (file_exists($target)) {
        unlink($target);
    }

    Artisan::call('vendor:publish', ['--tag' => 'proofread-pulse', '--force' => true]);

    expect(file_exists($target))->toBeTrue();
    $contents = (string) file_get_contents($target);
    expect($contents)->toContain('Proofread Evals');

    if (file_exists($target)) {
        unlink($target);
    }
});
