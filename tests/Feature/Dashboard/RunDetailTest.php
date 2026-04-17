<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Mosaiqo\Proofread\Http\Livewire\RunDetail;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

uses(RefreshDatabase::class);

function detailDataset(string $name = 'detail-dataset'): EvalDataset
{
    /** @var EvalDataset $dataset */
    $dataset = EvalDataset::query()->create([
        'name' => $name,
        'case_count' => 3,
        'checksum' => null,
    ]);

    return $dataset;
}

/**
 * @param  array<string, mixed>  $attrs
 */
function detailRun(array $attrs = []): EvalRun
{
    $datasetName = isset($attrs['dataset_name']) && is_string($attrs['dataset_name'])
        ? $attrs['dataset_name']
        : 'detail-ds-'.uniqid();

    $dataset = detailDataset($datasetName);

    /** @var EvalRun $run */
    $run = EvalRun::query()->create(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'agent',
        'subject_class' => 'App\\Agents\\FooAgent',
        'commit_sha' => 'abc1234',
        'model' => 'claude-sonnet',
        'passed' => true,
        'pass_count' => 3,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 3,
        'duration_ms' => 345.6,
        'total_cost_usd' => 0.0321,
        'total_tokens_in' => 400,
        'total_tokens_out' => 200,
    ], $attrs));

    return $run;
}

/**
 * @param  array<string, mixed>  $attrs
 */
function detailResult(EvalRun $run, array $attrs = []): EvalResult
{
    /** @var EvalResult $result */
    $result = EvalResult::query()->create(array_merge([
        'run_id' => $run->id,
        'case_index' => 0,
        'case_name' => 'Case zero',
        'input' => ['prompt' => 'hello'],
        'output' => 'hi there',
        'expected' => ['answer' => 'hi'],
        'passed' => true,
        'assertion_results' => [
            [
                'name' => 'contains',
                'passed' => true,
                'reason' => 'matched needle',
                'metadata' => [],
            ],
        ],
        'error_class' => null,
        'error_message' => null,
        'error_trace' => null,
        'duration_ms' => 12.5,
        'latency_ms' => 10.5,
        'tokens_in' => 100,
        'tokens_out' => 50,
        'cost_usd' => 0.0025,
        'model' => 'claude-sonnet',
        'created_at' => now(),
    ], $attrs));

    return $result;
}

it('renders the run detail page', function (): void {
    $run = detailRun();

    Livewire::test(RunDetail::class, ['run' => $run])
        ->assertOk()
        ->assertSee($run->dataset_name);
});

it('resolves the run by ULID via route binding', function (): void {
    $run = detailRun(['dataset_name' => 'route-bind-ds']);

    $response = $this->get('/evals/runs/'.$run->id);

    $response->assertOk();
    $response->assertSee('route-bind-ds');
});

it('404s when the run does not exist', function (): void {
    $response = $this->get('/evals/runs/01HXXXXXXXXXXXXXXXXXXXXXXX');

    $response->assertNotFound();
});

it('displays the dataset name in the breadcrumb', function (): void {
    $run = detailRun(['dataset_name' => 'breadcrumb-ds']);

    Livewire::test(RunDetail::class, ['run' => $run])
        ->assertSee('Runs')
        ->assertSee('breadcrumb-ds');
});

it('shows the summary stats from the run', function (): void {
    $run = detailRun([
        'pass_count' => 8,
        'fail_count' => 2,
        'error_count' => 1,
        'total_count' => 11,
        'duration_ms' => 1234.5,
        'total_cost_usd' => 0.4567,
    ]);

    Livewire::test(RunDetail::class, ['run' => $run])
        ->assertSee('8')
        ->assertSee('2')
        ->assertSee('1,234.5')
        ->assertSee('0.4567');
});

it('displays all cases by default', function (): void {
    $run = detailRun();
    $caseA = detailResult($run, ['case_index' => 0, 'case_name' => 'Alpha case', 'passed' => true]);
    $caseB = detailResult($run, ['case_index' => 1, 'case_name' => 'Beta case', 'passed' => false]);

    Livewire::test(RunDetail::class, ['run' => $run])
        ->assertSee('Alpha case')
        ->assertSee('Beta case');

    expect($caseA->id)->not->toBeEmpty();
    expect($caseB->id)->not->toBeEmpty();
});

it('filters to failing cases when onlyFailures is toggled', function (): void {
    $run = detailRun();
    detailResult($run, ['case_index' => 0, 'case_name' => 'Alpha case', 'passed' => true]);
    detailResult($run, ['case_index' => 1, 'case_name' => 'Beta case', 'passed' => false]);

    Livewire::test(RunDetail::class, ['run' => $run])
        ->call('toggleFailures')
        ->assertSet('onlyFailures', true)
        ->assertSee('Beta case')
        ->assertDontSee('Alpha case');
});

it('opens the drawer when a case is selected', function (): void {
    $run = detailRun();
    $result = detailResult($run, ['case_name' => 'Drawer case']);

    Livewire::test(RunDetail::class, ['run' => $run])
        ->call('selectCase', $result->id)
        ->assertSet('selectedCaseId', $result->id)
        ->assertSee('Drawer case');
});

it('closes the drawer via closeCase', function (): void {
    $run = detailRun();
    $result = detailResult($run, ['case_name' => 'Closable case']);

    Livewire::test(RunDetail::class, ['run' => $run])
        ->call('selectCase', $result->id)
        ->assertSet('selectedCaseId', $result->id)
        ->call('closeCase')
        ->assertSet('selectedCaseId', null);
});

it('shows the case input output expected and assertions in the drawer', function (): void {
    $run = detailRun();
    $result = detailResult($run, [
        'case_name' => 'Inspectable case',
        'input' => ['prompt' => 'ping'],
        'output' => 'pong',
        'expected' => ['answer' => 'pong'],
        'assertion_results' => [
            [
                'name' => 'contains',
                'passed' => true,
                'reason' => 'matched',
                'metadata' => ['needle' => 'pong'],
            ],
        ],
    ]);

    Livewire::test(RunDetail::class, ['run' => $run])
        ->call('selectCase', $result->id)
        ->assertSee('prompt')
        ->assertSee('ping')
        ->assertSee('pong')
        ->assertSee('answer')
        ->assertSee('contains')
        ->assertSee('matched');
});

it('shows the error details when a case has an error', function (): void {
    $run = detailRun();
    $result = detailResult($run, [
        'case_name' => 'Errored case',
        'passed' => false,
        'error_class' => 'RuntimeException',
        'error_message' => 'something went wrong',
        'error_trace' => "#0 /tmp/boom.php(42)\n#1 /tmp/other.php(7)",
    ]);

    Livewire::test(RunDetail::class, ['run' => $run])
        ->call('selectCase', $result->id)
        ->assertSee('RuntimeException')
        ->assertSee('something went wrong');
});

it('respects the viewEvals gate', function (): void {
    Gate::define('viewEvals', fn ($user = null) => false);

    $run = detailRun();

    $response = $this->get('/evals/runs/'.$run->id);

    $response->assertForbidden();
});

it('exposes metadata like latency, tokens, cost in the drawer', function (): void {
    $run = detailRun();
    $result = detailResult($run, [
        'case_name' => 'Metadata case',
        'latency_ms' => 42.5,
        'tokens_in' => 123,
        'tokens_out' => 77,
        'cost_usd' => 0.00456,
        'model' => 'claude-opus',
    ]);

    Livewire::test(RunDetail::class, ['run' => $run])
        ->call('selectCase', $result->id)
        ->assertSee('42.5')
        ->assertSee('123')
        ->assertSee('77')
        ->assertSee('0.00456')
        ->assertSee('claude-opus');
});

it('formats long output with a show-more toggle', function (): void {
    $run = detailRun();
    $longOutput = str_repeat('a', 1200);
    $result = detailResult($run, [
        'case_name' => 'Long output case',
        'output' => $longOutput,
    ]);

    $component = Livewire::test(RunDetail::class, ['run' => $run])
        ->call('selectCase', $result->id);

    $component->assertSee('Show more');
});

it('renders export buttons for markdown and HTML', function (): void {
    $run = detailRun(['dataset_name' => 'export-buttons-ds']);

    Livewire::test(RunDetail::class, ['run' => $run])
        ->assertSee('Export Markdown')
        ->assertSee('Export HTML')
        ->assertSee(route('proofread.runs.export', ['run' => $run->id, 'format' => 'md']), false)
        ->assertSee(route('proofread.runs.export', ['run' => $run->id, 'format' => 'html']), false);
});
