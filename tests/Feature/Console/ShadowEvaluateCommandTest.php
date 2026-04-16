<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Models\ShadowEval;
use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\Shadow\ShadowAssertionsRegistry;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\ShadowedEchoAgent;

/**
 * @param  array<string, mixed>  $overrides
 */
function seedCapture(array $overrides = []): ShadowCapture
{
    $defaults = [
        'agent_class' => ShadowedEchoAgent::class,
        'prompt_hash' => bin2hex(random_bytes(32)),
        'input_payload' => ['prompt' => 'hello'],
        'output' => 'some output',
        'tokens_in' => 10,
        'tokens_out' => 5,
        'cost_usd' => 0.001,
        'latency_ms' => 50.0,
        'model_used' => 'claude-haiku-4-5',
        'captured_at' => Carbon::now(),
        'sample_rate' => 1.0,
        'is_anonymized' => true,
    ];

    $capture = new ShadowCapture;
    $capture->fill(array_merge($defaults, $overrides));
    $capture->save();

    return $capture;
}

function passingAssertionResolver(): Closure
{
    return function (): array {
        return [
            new class implements Assertion
            {
                public function run(mixed $output, array $context = []): AssertionResult
                {
                    return AssertionResult::pass('ok');
                }

                public function name(): string
                {
                    return 'always-pass';
                }
            },
        ];
    };
}

function enableShadow(): void
{
    /** @var Repository $config */
    $config = app('config');
    $config->set('proofread.shadow.enabled', true);
}

function disableShadow(): void
{
    /** @var Repository $config */
    $config = app('config');
    $config->set('proofread.shadow.enabled', false);
}

beforeEach(function (): void {
    app()->forgetInstance(ShadowAssertionsRegistry::class);
    enableShadow();
});

it('evaluates pending captures', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, passingAssertionResolver());

    seedCapture();
    seedCapture();

    $exit = Artisan::call('shadow:evaluate');

    expect($exit)->toBe(0)
        ->and(ShadowEval::query()->count())->toBe(2);
});

it('filters by agent class', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, passingAssertionResolver());
    Proofread::registerShadowAssertions(EchoAgent::class, passingAssertionResolver());

    seedCapture(['agent_class' => ShadowedEchoAgent::class]);
    seedCapture(['agent_class' => EchoAgent::class]);

    $exit = Artisan::call('shadow:evaluate', [
        '--agent' => ShadowedEchoAgent::class,
    ]);

    expect($exit)->toBe(0)
        ->and(ShadowEval::query()->count())->toBe(1)
        ->and(ShadowEval::query()->where('agent_class', ShadowedEchoAgent::class)->count())->toBe(1);
});

it('filters by since duration', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, passingAssertionResolver());

    seedCapture(['captured_at' => Carbon::now()->subDays(3)]);
    seedCapture(['captured_at' => Carbon::now()->subMinutes(10)]);
    seedCapture(['captured_at' => Carbon::now()->subMinutes(5)]);

    $exit = Artisan::call('shadow:evaluate', [
        '--since' => '1h',
    ]);

    expect($exit)->toBe(0)
        ->and(ShadowEval::query()->count())->toBe(2);
});

it('respects batch limit', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, passingAssertionResolver());

    for ($i = 0; $i < 10; $i++) {
        seedCapture(['captured_at' => Carbon::now()->subMinutes($i)]);
    }

    $exit = Artisan::call('shadow:evaluate', [
        '--batch' => 3,
    ]);

    expect($exit)->toBe(0)
        ->and(ShadowEval::query()->count())->toBe(3);
});

it('skips already-evaluated captures by default', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, passingAssertionResolver());

    $capture = seedCapture();

    $eval = new ShadowEval;
    $eval->fill([
        'capture_id' => $capture->id,
        'agent_class' => $capture->agent_class,
        'passed' => true,
        'total_assertions' => 1,
        'passed_assertions' => 1,
        'failed_assertions' => 0,
        'assertion_results' => [],
        'evaluation_duration_ms' => 1.0,
        'evaluated_at' => Carbon::now(),
    ]);
    $eval->save();
    $originalId = $eval->id;

    $exit = Artisan::call('shadow:evaluate');

    expect($exit)->toBe(0)
        ->and(ShadowEval::query()->count())->toBe(1)
        ->and(ShadowEval::query()->firstOrFail()->id)->toBe($originalId);
});

it('re-evaluates with --force', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, passingAssertionResolver());

    $capture = seedCapture();

    $eval = new ShadowEval;
    $eval->fill([
        'capture_id' => $capture->id,
        'agent_class' => $capture->agent_class,
        'passed' => true,
        'total_assertions' => 1,
        'passed_assertions' => 1,
        'failed_assertions' => 0,
        'assertion_results' => [],
        'evaluation_duration_ms' => 1.0,
        'evaluated_at' => Carbon::now(),
    ]);
    $eval->save();
    $originalId = $eval->id;

    $exit = Artisan::call('shadow:evaluate', [
        '--force' => true,
    ]);

    expect($exit)->toBe(0)
        ->and(ShadowEval::query()->count())->toBe(1)
        ->and(ShadowEval::query()->firstOrFail()->id)->not->toBe($originalId);
});

it('prints summary with aggregates', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, passingAssertionResolver());

    seedCapture();
    seedCapture();

    Artisan::call('shadow:evaluate');
    $output = Artisan::output();

    expect($output)->toContain('Summary')
        ->and($output)->toContain('Processed:  2')
        ->and($output)->toContain('Skipped:    0')
        ->and($output)->toContain('Evals:      2 created')
        ->and($output)->toContain('Pass rate:  100.0%')
        ->and($output)->toContain('Judge cost: $0.0000');
});

it('exits 0 on success', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, passingAssertionResolver());

    seedCapture();

    $exit = Artisan::call('shadow:evaluate');

    expect($exit)->toBe(0);
});

it('warns and exits when shadow is disabled', function (): void {
    disableShadow();

    $exit = Artisan::call('shadow:evaluate');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Shadow capture is disabled');
});

it('prints No captures message when none match', function (): void {
    $exit = Artisan::call('shadow:evaluate');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('No captures to evaluate');
});

it('does not persist in --dry-run', function (): void {
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, passingAssertionResolver());

    seedCapture();
    seedCapture();

    $exit = Artisan::call('shadow:evaluate', [
        '--dry-run' => true,
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and(ShadowEval::query()->count())->toBe(0)
        ->and($output)->toContain('DRY RUN');
});

it('handles captures with agents that have no registered assertions', function (): void {
    // Only register for ShadowedEchoAgent; seed captures for both.
    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, passingAssertionResolver());

    seedCapture(['agent_class' => ShadowedEchoAgent::class]);
    seedCapture(['agent_class' => EchoAgent::class]);

    $exit = Artisan::call('shadow:evaluate');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and(ShadowEval::query()->count())->toBe(1)
        ->and($output)->toContain('Skipped');
});
