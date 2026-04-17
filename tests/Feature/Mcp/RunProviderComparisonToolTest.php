<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mosaiqo\Proofread\Mcp\Tools\RunProviderComparisonTool;
use Mosaiqo\Proofread\Models\EvalComparison;
use Mosaiqo\Proofread\Models\EvalRun;
use Mosaiqo\Proofread\Runner\Concurrency\ConcurrencyDriver;
use Mosaiqo\Proofread\Tests\Fixtures\Mcp\ProofreadMcpServer;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\MixedMultiSubjectSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\PassingMultiSubjectSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\PassingSuite;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function runProviderComparisonStructured(string $suiteClass, array $overrides = []): array
{
    $arguments = ['suite_class' => $suiteClass] + $overrides;

    $response = ProofreadMcpServer::tool(RunProviderComparisonTool::class, $arguments);

    $payload = (fn () => $this->response->toArray())->call($response);
    /** @var array<string, mixed> $structured */
    $structured = $payload['result']['structuredContent'] ?? [];

    return $structured;
}

it('runs a multi-subject suite via the tool', function (): void {
    $structured = runProviderComparisonStructured(PassingMultiSubjectSuite::class);

    expect($structured['suite_class'])->toBe(PassingMultiSubjectSuite::class)
        ->and($structured['name'])->toBe('passing-multi')
        ->and($structured['dataset_name'])->toBe('multi-data')
        ->and($structured['passed'])->toBeTrue()
        ->and($structured['total_cases'])->toBe(2)
        ->and($structured['persisted_comparison_id'])->toBeNull()
        ->and($structured['subjects'])->toBe(['haiku', 'sonnet', 'opus'])
        ->and($structured['runs'])->toHaveCount(3);
});

it('persists the comparison when persist is true', function (): void {
    $structured = runProviderComparisonStructured(
        PassingMultiSubjectSuite::class,
        ['persist' => true],
    );

    $comparisonId = $structured['persisted_comparison_id'];
    expect($comparisonId)->toBeString();

    $comparison = EvalComparison::query()->where('id', $comparisonId)->firstOrFail();
    expect($comparison->suite_class)->toBe(PassingMultiSubjectSuite::class);

    $runsCount = EvalRun::query()->where('comparison_id', $comparisonId)->count();
    expect($runsCount)->toBe(3);
});

it('includes commit_sha on persisted comparison', function (): void {
    $structured = runProviderComparisonStructured(
        PassingMultiSubjectSuite::class,
        ['persist' => true, 'commit_sha' => 'deadbeef'],
    );

    $comparisonId = (string) $structured['persisted_comparison_id'];
    $comparison = EvalComparison::query()->where('id', $comparisonId)->firstOrFail();

    expect($comparison->commit_sha)->toBe('deadbeef');
});

it('returns an error when the class does not exist', function (): void {
    $response = ProofreadMcpServer::tool(RunProviderComparisonTool::class, [
        'suite_class' => 'Some\\NonExistent\\Suite',
    ]);

    $response->assertHasErrors();
});

it('returns an error when the class is not a MultiSubjectEvalSuite', function (): void {
    $response = ProofreadMcpServer::tool(RunProviderComparisonTool::class, [
        'suite_class' => PassingSuite::class,
    ]);

    $response->assertHasErrors();
});

it('applies provider_concurrency', function (): void {
    $driver = new RecordingConcurrencyDriver;
    app()->instance(ConcurrencyDriver::class, $driver);

    runProviderComparisonStructured(
        PassingMultiSubjectSuite::class,
        ['provider_concurrency' => 2],
    );

    expect($driver->invocations)->toBeGreaterThan(0);
});

it('reports overall passed false when any subject fails', function (): void {
    $structured = runProviderComparisonStructured(MixedMultiSubjectSuite::class);

    expect($structured['passed'])->toBeFalse();
});

it('includes per-subject stats', function (): void {
    $structured = runProviderComparisonStructured(MixedMultiSubjectSuite::class);

    /** @var list<array<string, mixed>> $runs */
    $runs = $structured['runs'];

    $byLabel = [];
    foreach ($runs as $run) {
        $byLabel[$run['subject_label']] = $run;
    }

    expect($byLabel)->toHaveKeys(['good', 'bad']);

    $good = $byLabel['good'];
    expect($good)->toHaveKeys([
        'subject_label',
        'passed',
        'total_cases',
        'passed_cases',
        'failed_cases',
        'pass_rate',
        'cost_usd',
        'duration_ms',
        'avg_latency_ms',
    ])
        ->and($good['passed'])->toBeTrue()
        ->and($good['pass_rate'])->toEqualWithDelta(1.0, 0.0001)
        ->and($good['passed_cases'])->toBe(2)
        ->and($good['failed_cases'])->toBe(0);

    $bad = $byLabel['bad'];
    expect($bad['passed'])->toBeFalse()
        ->and($bad['pass_rate'])->toEqualWithDelta(0.0, 0.0001)
        ->and($bad['passed_cases'])->toBe(0)
        ->and($bad['failed_cases'])->toBe(2);
});

it('returns null for avg_latency_ms when results do not report latency', function (): void {
    $structured = runProviderComparisonStructured(PassingMultiSubjectSuite::class);

    /** @var list<array<string, mixed>> $runs */
    $runs = $structured['runs'];
    foreach ($runs as $run) {
        expect($run['avg_latency_ms'])->toBeNull();
    }
});

final class RecordingConcurrencyDriver implements ConcurrencyDriver
{
    public int $invocations = 0;

    public function run(array $tasks): array
    {
        $this->invocations++;

        $results = [];
        foreach ($tasks as $task) {
            $results[] = $task();
        }

        return $results;
    }
}
