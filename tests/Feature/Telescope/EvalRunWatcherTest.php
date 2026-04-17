<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Mosaiqo\Proofread\Events\EvalRunPersisted;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * @param  array<string, mixed>  $overrides
 */
function seedTelescopeRun(array $overrides = []): EvalRun
{
    $dataset = EvalDataset::query()->firstOrCreate(
        ['name' => $overrides['dataset_name'] ?? 'telescope-ds'],
        ['case_count' => 1, 'checksum' => hash('sha256', (string) ($overrides['dataset_name'] ?? 'telescope-ds'))],
    );

    $run = new EvalRun;
    $run->fill(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => 'telescope-ds',
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

beforeEach(function (): void {
    Telescope::afterRecording(static function (): void {});
    Telescope::startRecording(loadMonitoredTags: false);
});

afterEach(function (): void {
    Telescope::stopRecording();
    Telescope::afterRecording(static function (): void {});
});

it('records a Telescope entry when an eval run is persisted', function (): void {
    $captured = null;
    Telescope::afterRecording(function ($telescope, IncomingEntry $entry) use (&$captured): void {
        if (($entry->content['tags'] ?? null) === null && in_array('proofread_eval', $entry->tags, true)) {
            $captured = $entry;
        }
    });

    $run = seedTelescopeRun();

    Event::dispatch(new EvalRunPersisted($run));

    expect($captured)->not->toBeNull()
        ->and($captured)->toBeInstanceOf(IncomingEntry::class);
});

it('populates the entry content with run details', function (): void {
    /** @var list<IncomingEntry> $captured */
    $captured = [];
    Telescope::afterRecording(function ($telescope, IncomingEntry $entry) use (&$captured): void {
        if (in_array('proofread_eval', $entry->tags, true)) {
            $captured[] = $entry;
        }
    });

    $run = seedTelescopeRun([
        'dataset_name' => 'telescope-detail',
        'commit_sha' => 'cafef00d',
    ]);

    Event::dispatch(new EvalRunPersisted($run));

    expect($captured)->toHaveCount(1);
    $content = $captured[0]->content;
    expect($content)
        ->toHaveKey('eval_run_id', $run->id)
        ->toHaveKey('dataset_name', 'telescope-detail')
        ->toHaveKey('suite_class', 'App\\Evals\\ExampleSuite')
        ->toHaveKey('subject_class', 'App\\Agents\\ExampleAgent')
        ->toHaveKey('passed', true)
        ->toHaveKey('pass_count', 3)
        ->toHaveKey('fail_count', 0)
        ->toHaveKey('total_count', 3)
        ->toHaveKey('duration_ms', 1234.5)
        ->toHaveKey('total_cost_usd', 0.0123)
        ->toHaveKey('total_tokens_in', 500)
        ->toHaveKey('total_tokens_out', 120)
        ->toHaveKey('model', 'claude-haiku-4-5')
        ->toHaveKey('commit_sha', 'cafef00d');
});

it('tags the entry so it can be filtered by proofread_eval', function (): void {
    /** @var list<IncomingEntry> $captured */
    $captured = [];
    Telescope::afterRecording(function ($telescope, IncomingEntry $entry) use (&$captured): void {
        if (in_array('proofread_eval', $entry->tags, true)) {
            $captured[] = $entry;
        }
    });

    $run = seedTelescopeRun(['dataset_name' => 'telescope-tags']);

    Event::dispatch(new EvalRunPersisted($run));

    expect($captured)->toHaveCount(1);
    expect($captured[0]->tags)->toContain('proofread_eval')
        ->toContain('dataset:telescope-tags');
});

it('does not record when Telescope is paused', function (): void {
    Telescope::stopRecording();

    /** @var list<IncomingEntry> $captured */
    $captured = [];
    Telescope::afterRecording(function ($telescope, IncomingEntry $entry) use (&$captured): void {
        if (in_array('proofread_eval', $entry->tags, true)) {
            $captured[] = $entry;
        }
    });

    $run = seedTelescopeRun(['dataset_name' => 'telescope-paused']);

    Event::dispatch(new EvalRunPersisted($run));

    expect($captured)->toHaveCount(0);
});
