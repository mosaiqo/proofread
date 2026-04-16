<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Carbon;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Models\ShadowEval;
use Mosaiqo\Proofread\Shadow\ShadowAlert;
use Mosaiqo\Proofread\Shadow\ShadowAlertService;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\ShadowedEchoAgent;

/**
 * @param  array<string, mixed>  $overrides
 */
function seedAlertEval(array $overrides = []): ShadowEval
{
    $agentClass = $overrides['agent_class'] ?? ShadowedEchoAgent::class;
    $evaluatedAt = $overrides['evaluated_at'] ?? Carbon::now();

    $capture = new ShadowCapture;
    $capture->fill([
        'agent_class' => $agentClass,
        'prompt_hash' => bin2hex(random_bytes(32)),
        'input_payload' => ['prompt' => 'seed'],
        'output' => 'seed',
        'tokens_in' => 1,
        'tokens_out' => 1,
        'cost_usd' => 0.0,
        'latency_ms' => 1.0,
        'model_used' => 'claude-haiku-4-5',
        'captured_at' => $evaluatedAt,
        'sample_rate' => 1.0,
        'is_anonymized' => true,
    ]);
    $capture->save();

    $defaults = [
        'capture_id' => $capture->id,
        'agent_class' => $agentClass,
        'passed' => true,
        'total_assertions' => 1,
        'passed_assertions' => 1,
        'failed_assertions' => 0,
        'assertion_results' => [],
        'evaluation_duration_ms' => 1.0,
        'evaluated_at' => $evaluatedAt,
    ];

    $merged = array_merge($defaults, $overrides);
    $merged['capture_id'] = $capture->id;

    if (($merged['passed'] ?? true) === false && ! array_key_exists('passed_assertions', $overrides)) {
        $merged['passed_assertions'] = 0;
        $merged['failed_assertions'] = 1;
    }

    $eval = new ShadowEval;
    $eval->fill($merged);
    $eval->save();

    return $eval;
}

function makeAlertService(
    float $threshold = 0.85,
    string $window = '1h',
    int $minSamples = 10,
    string $dedupWindow = '1h',
): ShadowAlertService {
    /** @var CacheRepository $cache */
    $cache = app('cache.store');

    return new ShadowAlertService(
        cache: $cache,
        threshold: $threshold,
        window: $window,
        minSampleSize: $minSamples,
        dedupWindow: $dedupWindow,
    );
}

beforeEach(function (): void {
    /** @var ConfigRepository $config */
    $config = app('config');
    $config->set('cache.default', 'array');
    app('cache')->store('array')->flush();
});

it('returns no alerts when all agents are above threshold', function (): void {
    for ($i = 0; $i < 12; $i++) {
        seedAlertEval(['passed' => true]);
    }

    $service = makeAlertService();

    expect($service->check())->toBe([]);
});

it('returns an alert when pass rate drops below threshold', function (): void {
    for ($i = 0; $i < 8; $i++) {
        seedAlertEval(['passed' => true]);
    }
    for ($i = 0; $i < 4; $i++) {
        seedAlertEval(['passed' => false]);
    }

    $service = makeAlertService();
    $alerts = $service->check();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0])->toBeInstanceOf(ShadowAlert::class)
        ->and($alerts[0]->agentClass)->toBe(ShadowedEchoAgent::class)
        ->and($alerts[0]->passRate)->toEqualWithDelta(8 / 12, 0.0001)
        ->and($alerts[0]->threshold)->toBe(0.85);
});

it('skips agents with sample size below minimum', function (): void {
    for ($i = 0; $i < 3; $i++) {
        seedAlertEval(['passed' => false]);
    }
    for ($i = 0; $i < 2; $i++) {
        seedAlertEval(['passed' => true]);
    }

    $service = makeAlertService(minSamples: 10);

    expect($service->check())->toBe([]);
});

it('honors the window for computing pass rate', function (): void {
    for ($i = 0; $i < 20; $i++) {
        seedAlertEval([
            'passed' => false,
            'evaluated_at' => Carbon::now()->subDays(2),
        ]);
    }
    for ($i = 0; $i < 12; $i++) {
        seedAlertEval([
            'passed' => true,
            'evaluated_at' => Carbon::now()->subMinutes(10),
        ]);
    }

    $service = makeAlertService(window: '1h');

    expect($service->check())->toBe([]);
});

it('checks all agents when no class is specified', function (): void {
    for ($i = 0; $i < 12; $i++) {
        seedAlertEval([
            'agent_class' => ShadowedEchoAgent::class,
            'passed' => false,
        ]);
    }
    for ($i = 0; $i < 12; $i++) {
        seedAlertEval([
            'agent_class' => EchoAgent::class,
            'passed' => false,
        ]);
    }

    $service = makeAlertService();
    $alerts = $service->check();

    expect($alerts)->toHaveCount(2);

    $classes = array_map(fn (ShadowAlert $alert): string => $alert->agentClass, $alerts);
    expect($classes)->toContain(ShadowedEchoAgent::class)
        ->and($classes)->toContain(EchoAgent::class);
});

it('checks only the given agent when specified', function (): void {
    for ($i = 0; $i < 12; $i++) {
        seedAlertEval([
            'agent_class' => ShadowedEchoAgent::class,
            'passed' => false,
        ]);
    }
    for ($i = 0; $i < 12; $i++) {
        seedAlertEval([
            'agent_class' => EchoAgent::class,
            'passed' => false,
        ]);
    }

    $service = makeAlertService();
    $alerts = $service->check(ShadowedEchoAgent::class);

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->agentClass)->toBe(ShadowedEchoAgent::class);
});

it('suppresses alerts within the dedup window', function (): void {
    for ($i = 0; $i < 12; $i++) {
        seedAlertEval(['passed' => false]);
    }

    $service = makeAlertService(dedupWindow: '1h');

    $first = $service->check();
    expect($first)->toHaveCount(1);

    $service->markAlerted($first[0]);

    $second = $service->check();
    expect($second)->toBe([]);
});

it('alerts again after the dedup window expires', function (): void {
    for ($i = 0; $i < 12; $i++) {
        seedAlertEval(['passed' => false]);
    }

    $service = makeAlertService(dedupWindow: '1h');

    $first = $service->check();
    expect($first)->toHaveCount(1);

    $service->markAlerted($first[0]);

    Carbon::setTestNow(Carbon::now()->addHours(2));

    // Re-seed more failures inside the new window to keep the query non-empty.
    for ($i = 0; $i < 12; $i++) {
        seedAlertEval([
            'passed' => false,
            'evaluated_at' => Carbon::now()->subMinutes(5),
        ]);
    }

    $third = $service->check();
    expect($third)->toHaveCount(1);

    Carbon::setTestNow();
});

it('includes the window boundaries in the alert', function (): void {
    for ($i = 0; $i < 12; $i++) {
        seedAlertEval(['passed' => false]);
    }

    $service = makeAlertService(window: '1h');
    $alerts = $service->check();

    expect($alerts)->toHaveCount(1);
    $alert = $alerts[0];
    $diff = $alert->windowTo->getTimestamp() - $alert->windowFrom->getTimestamp();

    expect($diff)->toBeGreaterThanOrEqual(3500)
        ->and($diff)->toBeLessThanOrEqual(3700);
});

it('includes pass and fail counts in the alert', function (): void {
    for ($i = 0; $i < 4; $i++) {
        seedAlertEval(['passed' => true]);
    }
    for ($i = 0; $i < 8; $i++) {
        seedAlertEval(['passed' => false]);
    }

    $service = makeAlertService();
    $alerts = $service->check();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->sampleSize)->toBe(12)
        ->and($alerts[0]->passedCount)->toBe(4)
        ->and($alerts[0]->failedCount)->toBe(8);
});

it('computes pass rate correctly with mixed results', function (): void {
    for ($i = 0; $i < 8; $i++) {
        seedAlertEval(['passed' => true]);
    }
    for ($i = 0; $i < 2; $i++) {
        seedAlertEval(['passed' => false]);
    }

    $service = makeAlertService(threshold: 0.85, minSamples: 10);
    $alerts = $service->check();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->passRate)->toEqualWithDelta(0.8, 0.0001);
});

it('builds from config with defaults', function (): void {
    /** @var CacheRepository $cache */
    $cache = app('cache.store');

    $service = ShadowAlertService::fromConfig($cache, [
        'enabled' => true,
        'pass_rate_threshold' => 0.9,
        'window' => '2h',
        'min_sample_size' => 5,
        'dedup_window' => '30m',
    ]);

    for ($i = 0; $i < 5; $i++) {
        seedAlertEval(['passed' => false]);
    }

    $alerts = $service->check();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->threshold)->toBe(0.9);
});

it('marks an agent as alerted via markAlerted', function (): void {
    for ($i = 0; $i < 12; $i++) {
        seedAlertEval(['passed' => false]);
    }

    $service = makeAlertService();

    $alerts = $service->check();
    expect($alerts)->toHaveCount(1);

    $service->markAlerted($alerts[0]);

    $again = $service->check();
    expect($again)->toBe([]);
});
