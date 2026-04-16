<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mosaiqo\Proofread\Mcp\Tools\RunEvalSuiteTool;
use Mosaiqo\Proofread\Models\EvalRun;
use Mosaiqo\Proofread\Tests\Fixtures\Mcp\ProofreadMcpServer;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\ErroringSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\FailingSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\ManyFailuresSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\PassingSuite;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function runEvalSuiteStructured(string $suiteClass, bool $persist = false, ?string $commitSha = null): array
{
    $arguments = ['suite_class' => $suiteClass, 'persist' => $persist];
    if ($commitSha !== null) {
        $arguments['commit_sha'] = $commitSha;
    }

    $response = ProofreadMcpServer::tool(RunEvalSuiteTool::class, $arguments);

    $payload = (fn () => $this->response->toArray())->call($response);
    /** @var array<string, mixed> $structured */
    $structured = $payload['result']['structuredContent'] ?? [];

    return $structured;
}

it('runs a suite and returns a success summary', function (): void {
    $structured = runEvalSuiteStructured(PassingSuite::class, persist: false);

    expect($structured['suite_class'])->toBe(PassingSuite::class)
        ->and($structured['dataset_name'])->toBe('passing')
        ->and($structured['passed'])->toBeTrue()
        ->and($structured['total_cases'])->toBe(2)
        ->and($structured['passed_count'])->toBe(2)
        ->and($structured['failed_count'])->toBe(0)
        ->and($structured['failures'])->toBe([])
        ->and($structured['persisted_run_id'])->toBeNull();
});

it('returns failure details for a failing suite', function (): void {
    $structured = runEvalSuiteStructured(FailingSuite::class);

    expect($structured['passed'])->toBeFalse()
        ->and($structured['failed_count'])->toBe(1)
        ->and($structured['failures'])->toBeArray()
        ->and($structured['failures'])->toHaveCount(1);

    $failure = $structured['failures'][0];
    expect($failure)->toHaveKeys(['case_index', 'case_name', 'assertions_failed'])
        ->and($failure['case_index'])->toBe(1)
        ->and($failure['assertions_failed'])->toBeArray()
        ->and($failure['assertions_failed'][0])->toContain('contains');
});

it('includes case names in failure details', function (): void {
    $structured = runEvalSuiteStructured(FailingSuite::class);

    expect($structured['failures'][0]['case_name'])->toBe('second-case');
});

it('truncates failures when exceeding 10', function (): void {
    $structured = runEvalSuiteStructured(ManyFailuresSuite::class);

    expect($structured['failed_count'])->toBe(15)
        ->and($structured['failures'])->toHaveCount(10)
        ->and($structured['failures_truncated'])->toBeTrue()
        ->and($structured['failures_omitted'])->toBe(5);
});

it('persists the run when persist is true', function (): void {
    $structured = runEvalSuiteStructured(PassingSuite::class, persist: true);

    expect($structured['persisted_run_id'])->toBeString();

    $runId = (string) $structured['persisted_run_id'];
    $model = EvalRun::query()->where('id', $runId)->firstOrFail();

    expect($model->suite_class)->toBe(PassingSuite::class);
});

it('includes commit_sha on the persisted run', function (): void {
    $structured = runEvalSuiteStructured(
        PassingSuite::class,
        persist: true,
        commitSha: 'abc1234',
    );

    $runId = (string) $structured['persisted_run_id'];
    $model = EvalRun::query()->where('id', $runId)->firstOrFail();

    expect($model->commit_sha)->toBe('abc1234');
});

it('returns an error for a non-existent suite class', function (): void {
    $response = ProofreadMcpServer::tool(RunEvalSuiteTool::class, [
        'suite_class' => 'Some\\NonExistent\\Suite',
    ]);

    $response->assertHasErrors();
});

it('returns an error for a class that does not extend EvalSuite', function (): void {
    $response = ProofreadMcpServer::tool(RunEvalSuiteTool::class, [
        'suite_class' => DateTime::class,
    ]);

    $response->assertHasErrors();
});

it('includes dataset_name total_cases and duration_ms in the structured output', function (): void {
    $structured = runEvalSuiteStructured(PassingSuite::class);

    expect($structured)->toHaveKeys(['dataset_name', 'total_cases', 'duration_ms'])
        ->and($structured['dataset_name'])->toBe('passing')
        ->and($structured['total_cases'])->toBe(2)
        ->and($structured['duration_ms'])->toBeFloat()
        ->and($structured['duration_ms'])->toBeGreaterThanOrEqual(0.0);
});

it('reports total_cost_usd null when no cost tracking', function (): void {
    $structured = runEvalSuiteStructured(PassingSuite::class);

    expect($structured['total_cost_usd'])->toBeNull();
});

it('surfaces case errors separately from assertion failures', function (): void {
    $structured = runEvalSuiteStructured(ErroringSuite::class);

    expect($structured['passed'])->toBeFalse()
        ->and($structured['failed_count'])->toBe(1)
        ->and($structured['failures'])->toHaveCount(1);

    $failure = $structured['failures'][0];
    expect($failure['case_name'])->toBe('boom-case')
        ->and($failure['assertions_failed'])->toBeArray();

    $joined = implode(' | ', $failure['assertions_failed']);
    expect($joined)->toContain('subject exploded');
});
