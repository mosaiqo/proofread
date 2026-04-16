<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Models\ShadowEval;
use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\Shadow\ShadowAssertionsRegistry;
use Mosaiqo\Proofread\Shadow\ShadowEvaluationSummary;
use Mosaiqo\Proofread\Shadow\ShadowEvaluator;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\ShadowedEchoAgent;

/**
 * @param  array<string, mixed>  $overrides
 */
function makeShadowCapture(array $overrides = []): ShadowCapture
{
    $defaults = [
        'agent_class' => ShadowedEchoAgent::class,
        'prompt_hash' => str_repeat('a', 64),
        'input_payload' => ['prompt' => 'hello world'],
        'output' => 'the output text',
        'tokens_in' => 100,
        'tokens_out' => 50,
        'cost_usd' => 0.001234,
        'latency_ms' => 123.456,
        'model_used' => 'claude-haiku-4-5',
        'captured_at' => Carbon::parse('2026-04-15 12:00:00'),
        'sample_rate' => 1.0,
        'is_anonymized' => true,
    ];

    $capture = new ShadowCapture;
    $capture->fill(array_merge($defaults, $overrides));
    $capture->save();

    return $capture;
}

/**
 * Build an anonymous Assertion that returns a pre-built result.
 */
function makeFakeAssertion(string $name, AssertionResult $result): Assertion
{
    return new class($name, $result) implements Assertion
    {
        public function __construct(
            private readonly string $assertionName,
            private readonly AssertionResult $result,
        ) {}

        public function run(mixed $output, array $context = []): AssertionResult
        {
            return $this->result;
        }

        public function name(): string
        {
            return $this->assertionName;
        }
    };
}

function makeThrowingAssertion(string $name, string $message): Assertion
{
    return new class($name, $message) implements Assertion
    {
        public function __construct(
            private readonly string $assertionName,
            private readonly string $message,
        ) {}

        public function run(mixed $output, array $context = []): AssertionResult
        {
            throw new RuntimeException($this->message);
        }

        public function name(): string
        {
            return $this->assertionName;
        }
    };
}

/**
 * @param  Closure(mixed, array<string, mixed>): AssertionResult  $callback
 */
function makeInspectingAssertion(string $name, Closure $callback): Assertion
{
    return new class($name, $callback) implements Assertion
    {
        /**
         * @param  Closure(mixed, array<string, mixed>): AssertionResult  $callback
         */
        public function __construct(
            private readonly string $assertionName,
            private readonly Closure $callback,
        ) {}

        public function run(mixed $output, array $context = []): AssertionResult
        {
            return ($this->callback)($output, $context);
        }

        public function name(): string
        {
            return $this->assertionName;
        }
    };
}

beforeEach(function (): void {
    // Reset registry between tests since it's a singleton.
    app()->forgetInstance(ShadowAssertionsRegistry::class);
});

it('evaluates a capture against its registered assertions', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        makeFakeAssertion('fake-pass', AssertionResult::pass('ok')),
    ]);

    $capture = makeShadowCapture();

    $evaluator = app(ShadowEvaluator::class);
    $returned = $evaluator->evaluateOne($capture);

    $eval = ShadowEval::query()->firstOrFail();

    expect($returned)->toBeInstanceOf(ShadowEval::class)
        ->and($eval->passed)->toBeTrue()
        ->and($eval->total_assertions)->toBe(1)
        ->and($eval->passed_assertions)->toBe(1)
        ->and($eval->failed_assertions)->toBe(0);
});

it('persists a ShadowEval with all fields', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        makeFakeAssertion('fake', AssertionResult::pass('ok')),
    ]);

    $capture = makeShadowCapture();

    $evaluator = app(ShadowEvaluator::class);
    $evaluator->evaluateOne($capture);

    $eval = ShadowEval::query()->firstOrFail();

    expect($eval->capture_id)->toBe($capture->id)
        ->and($eval->agent_class)->toBe(ShadowedEchoAgent::class)
        ->and($eval->assertion_results)->toBeArray()
        ->and($eval->evaluated_at)->not->toBeNull()
        ->and($eval->evaluation_duration_ms)->toBeGreaterThanOrEqual(0.0);
});

it('returns null when no assertions are registered for the agent', function (): void {
    $capture = makeShadowCapture();

    $evaluator = app(ShadowEvaluator::class);
    $eval = $evaluator->evaluateOne($capture);

    expect($eval)->toBeNull()
        ->and(ShadowEval::query()->count())->toBe(0);
});

it('skips captures whose agent has no assertions', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        makeFakeAssertion('fake', AssertionResult::pass('ok')),
    ]);

    $withRegistry = makeShadowCapture();
    $withoutRegistry = makeShadowCapture(['agent_class' => EchoAgent::class]);

    $evaluator = app(ShadowEvaluator::class);
    $summary = $evaluator->evaluate([$withRegistry, $withoutRegistry]);

    expect($summary->capturesProcessed)->toBe(2)
        ->and($summary->capturesSkipped)->toBe(1)
        ->and($summary->evalsCreated)->toBe(1);
});

it('counts passed and failed assertions correctly', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        makeFakeAssertion('pass-1', AssertionResult::pass('ok')),
        makeFakeAssertion('pass-2', AssertionResult::pass('ok')),
        makeFakeAssertion('fail-1', AssertionResult::fail('nope')),
    ]);

    $capture = makeShadowCapture();

    $evaluator = app(ShadowEvaluator::class);
    $evaluator->evaluateOne($capture);

    $eval = ShadowEval::query()->firstOrFail();

    expect($eval->total_assertions)->toBe(3)
        ->and($eval->passed_assertions)->toBe(2)
        ->and($eval->failed_assertions)->toBe(1)
        ->and($eval->passed)->toBeFalse();
});

it('aggregates judge cost across assertions', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        makeFakeAssertion('j1', AssertionResult::pass('ok', null, ['judge_cost_usd' => 0.0012])),
        makeFakeAssertion('j2', AssertionResult::pass('ok', null, ['judge_cost_usd' => 0.0030])),
        makeFakeAssertion('j3', AssertionResult::pass('ok', null, [])),
    ]);

    $capture = makeShadowCapture();

    $evaluator = app(ShadowEvaluator::class);
    $evaluator->evaluateOne($capture);

    $eval = ShadowEval::query()->firstOrFail();

    expect($eval->judge_cost_usd)->toBeFloat()
        ->and($eval->judge_cost_usd)->toEqualWithDelta(0.0042, 0.00001);
});

it('aggregates judge tokens across assertions', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        makeFakeAssertion('j1', AssertionResult::pass('ok', null, ['judge_tokens_in' => 100, 'judge_tokens_out' => 20])),
        makeFakeAssertion('j2', AssertionResult::pass('ok', null, ['judge_tokens_in' => 50, 'judge_tokens_out' => 5])),
    ]);

    $capture = makeShadowCapture();

    $evaluator = app(ShadowEvaluator::class);
    $evaluator->evaluateOne($capture);

    $eval = ShadowEval::query()->firstOrFail();

    expect($eval->judge_tokens_in)->toBe(150)
        ->and($eval->judge_tokens_out)->toBe(25);
});

it('passes the capture context to assertions', function (): void {
    /** @var array<int, array<string, mixed>> $collected */
    $collected = [];

    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, function () use (&$collected): array {
        return [
            makeInspectingAssertion('inspector', function (mixed $output, array $context) use (&$collected): AssertionResult {
                $collected[] = $context;

                return AssertionResult::pass('ok');
            }),
        ];
    });

    $capture = makeShadowCapture([
        'latency_ms' => 555.5,
        'cost_usd' => 0.02,
        'model_used' => 'claude-sonnet-4-5',
        'tokens_in' => 77,
        'tokens_out' => 11,
    ]);

    $evaluator = app(ShadowEvaluator::class);
    $evaluator->evaluateOne($capture);

    expect($collected)->toHaveCount(1);
    $received = $collected[0];

    expect($received['latency_ms'])->toBe(555.5)
        ->and($received['cost_usd'])->toBe(0.02)
        ->and($received['model'])->toBe('claude-sonnet-4-5')
        ->and($received['tokens_in'])->toBe(77)
        ->and($received['tokens_out'])->toBe(11)
        ->and($received['shadow_capture_id'])->toBe($capture->id)
        ->and($received['input'])->toBe($capture->input_payload)
        ->and($received['case_index'])->toBe(0);
});

it('captures assertion exceptions as failed results', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        makeFakeAssertion('good', AssertionResult::pass('ok')),
        makeThrowingAssertion('bad', 'boom'),
    ]);

    $capture = makeShadowCapture();

    $evaluator = app(ShadowEvaluator::class);
    $evaluator->evaluateOne($capture);

    $eval = ShadowEval::query()->firstOrFail();

    expect($eval->total_assertions)->toBe(2)
        ->and($eval->passed_assertions)->toBe(1)
        ->and($eval->failed_assertions)->toBe(1)
        ->and($eval->passed)->toBeFalse();

    $badResult = null;
    foreach ($eval->assertion_results as $row) {
        if (($row['name'] ?? null) === 'bad') {
            $badResult = $row;
            break;
        }
    }

    expect($badResult)->not->toBeNull();
    /** @var array<string, mixed> $badResult */
    expect($badResult['passed'])->toBeFalse()
        ->and($badResult['reason'])->toContain('boom')
        ->and($badResult['reason'])->toContain('bad');
});

it('is idempotent — does not re-evaluate by default', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        makeFakeAssertion('fake', AssertionResult::pass('ok')),
    ]);

    $capture = makeShadowCapture();

    $evaluator = app(ShadowEvaluator::class);
    $evaluator->evaluateOne($capture);
    $firstId = ShadowEval::query()->firstOrFail()->id;

    $evaluator->evaluateOne($capture);
    $secondId = ShadowEval::query()->firstOrFail()->id;

    expect($firstId)->toBe($secondId)
        ->and(ShadowEval::query()->count())->toBe(1);
});

it('re-evaluates when force is true', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        makeFakeAssertion('fake', AssertionResult::pass('ok')),
    ]);

    $capture = makeShadowCapture();

    $evaluator = app(ShadowEvaluator::class);
    $evaluator->evaluateOne($capture);
    $firstId = ShadowEval::query()->firstOrFail()->id;

    $evaluator->evaluateOne($capture, force: true);
    $secondId = ShadowEval::query()->firstOrFail()->id;

    expect($secondId)->not->toBe($firstId)
        ->and(ShadowEval::query()->count())->toBe(1);
});

it('measures evaluation duration', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        makeFakeAssertion('fake', AssertionResult::pass('ok')),
    ]);

    $capture = makeShadowCapture();

    $evaluator = app(ShadowEvaluator::class);
    $evaluator->evaluateOne($capture);

    $eval = ShadowEval::query()->firstOrFail();

    expect($eval->evaluation_duration_ms)->toBeGreaterThanOrEqual(0.0);
});

it('includes assertion name in assertion_results JSON', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        makeFakeAssertion('first', AssertionResult::pass('ok-1', 0.9)),
        makeFakeAssertion('second', AssertionResult::fail('bad-2')),
    ]);

    $capture = makeShadowCapture();

    $evaluator = app(ShadowEvaluator::class);
    $evaluator->evaluateOne($capture);

    $eval = ShadowEval::query()->firstOrFail();
    $names = array_column($eval->assertion_results, 'name');
    expect($names)->toContain('first')
        ->and($names)->toContain('second');

    $first = null;
    foreach ($eval->assertion_results as $row) {
        if (($row['name'] ?? null) === 'first') {
            $first = $row;
            break;
        }
    }

    expect($first)->not->toBeNull();
    /** @var array<string, mixed> $first */
    expect($first['passed'])->toBeTrue()
        ->and($first['reason'])->toBe('ok-1')
        ->and($first['score'])->toBe(0.9);
});

it('sets overall passed true only when all assertions pass', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        makeFakeAssertion('a', AssertionResult::pass('ok')),
        makeFakeAssertion('b', AssertionResult::pass('ok')),
    ]);

    $capture = makeShadowCapture();

    $evaluator = app(ShadowEvaluator::class);
    $evaluator->evaluateOne($capture);

    $eval = ShadowEval::query()->firstOrFail();

    expect($eval->passed)->toBeTrue();
});

it('returns a summary with aggregate counts', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, function (): array {
        static $call = 0;
        $call++;

        return [
            // Alternate: the third capture will get a failing assertion.
            makeFakeAssertion('dyn', $call === 3 ? AssertionResult::fail('nope') : AssertionResult::pass('ok')),
        ];
    });

    $captures = [
        makeShadowCapture(['prompt_hash' => str_repeat('1', 64)]),
        makeShadowCapture(['prompt_hash' => str_repeat('2', 64)]),
        makeShadowCapture(['prompt_hash' => str_repeat('3', 64)]),
    ];

    $evaluator = app(ShadowEvaluator::class);
    $summary = $evaluator->evaluate($captures);

    expect($summary)->toBeInstanceOf(ShadowEvaluationSummary::class)
        ->and($summary->capturesProcessed)->toBe(3)
        ->and($summary->capturesSkipped)->toBe(0)
        ->and($summary->evalsCreated)->toBe(3)
        ->and($summary->passed + $summary->failed)->toBe(3);
});

it('tracks captures skipped in summary', function (): void {
    $captures = [
        makeShadowCapture(['agent_class' => EchoAgent::class, 'prompt_hash' => str_repeat('1', 64)]),
        makeShadowCapture(['agent_class' => EchoAgent::class, 'prompt_hash' => str_repeat('2', 64)]),
    ];

    $evaluator = app(ShadowEvaluator::class);
    $summary = $evaluator->evaluate($captures);

    expect($summary->capturesProcessed)->toBe(2)
        ->and($summary->capturesSkipped)->toBe(2)
        ->and($summary->evalsCreated)->toBe(0);
});

it('computes passRate on the summary value object', function (): void {
    $summary = new ShadowEvaluationSummary(
        capturesProcessed: 10,
        capturesSkipped: 2,
        evalsCreated: 8,
        passed: 6,
        failed: 2,
        totalJudgeCostUsd: 0.01,
        durationMs: 123.4,
    );

    expect($summary->passRate())->toEqualWithDelta(0.75, 0.001);
});

it('passRate returns 1.0 when no evals were created', function (): void {
    $summary = new ShadowEvaluationSummary(
        capturesProcessed: 0,
        capturesSkipped: 0,
        evalsCreated: 0,
        passed: 0,
        failed: 0,
        totalJudgeCostUsd: 0.0,
        durationMs: 0.0,
    );

    expect($summary->passRate())->toBe(1.0);
});
