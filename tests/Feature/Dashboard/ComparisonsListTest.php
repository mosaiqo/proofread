<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use Mosaiqo\Proofread\Http\Livewire\ComparisonsList;
use Mosaiqo\Proofread\Models\EvalComparison;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attrs
 */
function makeComparison(array $attrs = []): EvalComparison
{
    /** @var EvalComparison $comparison */
    $comparison = EvalComparison::query()->create(array_merge([
        'name' => 'cmp-'.uniqid(),
        'suite_class' => null,
        'dataset_name' => 'ds-'.uniqid(),
        'dataset_version_id' => null,
        'subject_labels' => ['haiku', 'sonnet'],
        'commit_sha' => null,
        'total_runs' => 2,
        'passed_runs' => 2,
        'failed_runs' => 0,
        'total_cost_usd' => 0.1234,
        'duration_ms' => 500.5,
    ], $attrs));

    return $comparison;
}

it('renders the comparisons list view', function (): void {
    Livewire::test(ComparisonsList::class)
        ->assertOk()
        ->assertSee('Comparisons');
});

it('displays comparisons in created_at desc order', function (): void {
    $older = makeComparison(['name' => 'older-cmp']);
    $older->created_at = now()->subHours(2);
    $older->save();

    $newer = makeComparison(['name' => 'newer-cmp']);
    $newer->created_at = now()->subMinutes(5);
    $newer->save();

    Livewire::test(ComparisonsList::class)
        ->assertSee('older-cmp')
        ->assertSee('newer-cmp')
        ->assertSeeInOrder(['newer-cmp', 'older-cmp']);
});

it('shows total count stat', function (): void {
    makeComparison();
    makeComparison();
    makeComparison();

    Livewire::test(ComparisonsList::class)
        ->assertSee('Total comparisons', false)
        ->assertSet('stats.total', 3);
});

it('shows 7-day pass rate stat', function (): void {
    $cmp1 = makeComparison(['total_runs' => 2, 'passed_runs' => 2, 'failed_runs' => 0]);
    $cmp1->created_at = now()->subDays(2);
    $cmp1->save();

    $cmp2 = makeComparison(['total_runs' => 2, 'passed_runs' => 1, 'failed_runs' => 1]);
    $cmp2->created_at = now()->subDays(3);
    $cmp2->save();

    $old = makeComparison(['total_runs' => 2, 'passed_runs' => 2, 'failed_runs' => 0]);
    $old->created_at = now()->subDays(20);
    $old->save();

    Livewire::test(ComparisonsList::class)
        ->assertSee('Pass rate', false)
        ->assertSet('stats.seven_day_pass_rate', 0.5);
});

it('shows active datasets stat', function (): void {
    $recent = makeComparison(['dataset_name' => 'recent-ds']);
    $recent->created_at = now()->subDays(5);
    $recent->save();

    $recent2 = makeComparison(['dataset_name' => 'recent-ds-2']);
    $recent2->created_at = now()->subDays(10);
    $recent2->save();

    $stale = makeComparison(['dataset_name' => 'stale-ds']);
    $stale->created_at = now()->subDays(45);
    $stale->save();

    Livewire::test(ComparisonsList::class)
        ->assertSee('Active datasets', false)
        ->assertSet('stats.active_datasets', 2);
});

it('filters by dataset', function (): void {
    $keep = makeComparison(['name' => 'keep-me', 'dataset_name' => 'foo-dataset']);
    $drop = makeComparison(['name' => 'drop-me', 'dataset_name' => 'bar-dataset']);

    Livewire::test(ComparisonsList::class)
        ->set('datasetFilter', 'foo-dataset')
        ->assertSee('keep-me')
        ->assertDontSee('drop-me');

    expect($keep->id)->not->toBeEmpty();
    expect($drop->id)->not->toBeEmpty();
});

it('filters by status passed', function (): void {
    makeComparison(['name' => 'all-passed', 'total_runs' => 2, 'passed_runs' => 2, 'failed_runs' => 0]);
    makeComparison(['name' => 'has-failure', 'total_runs' => 2, 'passed_runs' => 1, 'failed_runs' => 1]);

    Livewire::test(ComparisonsList::class)
        ->set('statusFilter', 'passed')
        ->assertSee('all-passed')
        ->assertDontSee('has-failure');
});

it('filters by status failed', function (): void {
    makeComparison(['name' => 'all-passed', 'total_runs' => 2, 'passed_runs' => 2, 'failed_runs' => 0]);
    makeComparison(['name' => 'has-failure', 'total_runs' => 2, 'passed_runs' => 1, 'failed_runs' => 1]);

    Livewire::test(ComparisonsList::class)
        ->set('statusFilter', 'failed')
        ->assertSee('has-failure')
        ->assertDontSee('all-passed');
});

it('searches by name or dataset substring', function (): void {
    $nameHit = makeComparison(['name' => 'sentiment-comparison', 'dataset_name' => 'unrelated']);
    $datasetHit = makeComparison(['name' => 'unrelated-cmp', 'dataset_name' => 'sentiment-dataset']);
    $noHit = makeComparison(['name' => 'other-cmp', 'dataset_name' => 'other-ds']);

    Livewire::test(ComparisonsList::class)
        ->set('search', 'sentiment')
        ->assertSee('sentiment-comparison')
        ->assertSee('sentiment-dataset')
        ->assertDontSee('other-cmp');

    expect($nameHit->id)->not->toBeEmpty();
    expect($datasetHit->id)->not->toBeEmpty();
    expect($noHit->id)->not->toBeEmpty();
});

it('paginates when comparisons exceed page size', function (): void {
    $comparisons = [];
    for ($i = 0; $i < 25; $i++) {
        $comparisons[$i] = makeComparison(['name' => "cmp-page-$i"]);
        $comparisons[$i]->created_at = now()->subSeconds(25 - $i);
        $comparisons[$i]->save();
    }

    Livewire::test(ComparisonsList::class)
        ->assertSee('cmp-page-24')
        ->assertDontSee('cmp-page-0"');
});

it('shows an empty state when there are no comparisons', function (): void {
    Livewire::test(ComparisonsList::class)
        ->assertSee('No comparisons yet');
});

it('links to the comparison detail view', function (): void {
    $cmp = makeComparison(['name' => 'link-cmp']);

    Livewire::test(ComparisonsList::class)
        ->assertSee('/evals/comparisons/'.$cmp->id, false);
});

it('displays subject labels as pills', function (): void {
    makeComparison([
        'name' => 'pill-cmp',
        'subject_labels' => ['haiku', 'sonnet', 'opus'],
    ]);

    Livewire::test(ComparisonsList::class)
        ->assertSee('subject-pill', false)
        ->assertSee('haiku')
        ->assertSee('sonnet')
        ->assertSee('opus');
});

it('truncates subject pills when there are many', function (): void {
    makeComparison([
        'name' => 'many-pills',
        'subject_labels' => ['a', 'b', 'c', 'd', 'e', 'f'],
    ]);

    Livewire::test(ComparisonsList::class)
        ->assertSee('+3 more')
        ->assertSee('a')
        ->assertSee('b')
        ->assertSee('c');
});

it('respects the viewEvals gate', function (): void {
    Gate::define('viewEvals', fn ($user = null) => false);

    $response = $this->get('/evals/comparisons');

    $response->assertForbidden();
});

it('exposes filter state in URL query string', function (): void {
    $list = new ComparisonsList;
    $reflection = new ReflectionClass($list);

    foreach (['datasetFilter', 'statusFilter', 'search'] as $property) {
        $attributes = $reflection->getProperty($property)->getAttributes(Url::class);
        expect($attributes)->not->toBeEmpty();
    }
});
