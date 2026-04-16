<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Models\ShadowEval;
use Mosaiqo\Proofread\Shadow\Notifications\ShadowPassRateDroppedNotification;
use Mosaiqo\Proofread\Shadow\ShadowAlertService;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\ShadowedEchoAgent;

/**
 * @param  array<string, mixed>  $overrides
 */
function seedAlertCommandEval(array $overrides = []): ShadowEval
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

function enableAlerts(
    float $threshold = 0.85,
    string $window = '1h',
    int $minSamples = 10,
    string $dedupWindow = '1h',
    ?string $mailTo = 'ops@example.com',
): void {
    /** @var ConfigRepository $config */
    $config = app('config');
    $config->set('cache.default', 'array');
    $config->set('proofread.shadow.alerts', [
        'enabled' => true,
        'pass_rate_threshold' => $threshold,
        'window' => $window,
        'min_sample_size' => $minSamples,
        'dedup_window' => $dedupWindow,
        'channels' => ['mail'],
        'mail' => [
            'to' => $mailTo,
        ],
        'slack' => [
            'webhook_url' => null,
        ],
    ]);
    app('cache')->store('array')->flush();
    app()->forgetInstance(ShadowAlertService::class);
}

function disableAlerts(): void
{
    /** @var ConfigRepository $config */
    $config = app('config');
    $config->set('proofread.shadow.alerts.enabled', false);
    app()->forgetInstance(ShadowAlertService::class);
}

beforeEach(function (): void {
    enableAlerts();
});

it('does nothing when alerts are disabled', function (): void {
    Notification::fake();
    disableAlerts();

    $exit = Artisan::call('shadow:alert');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('alerts are disabled');

    Notification::assertNothingSent();
});

it('prints no-alerts message when pass rate is healthy', function (): void {
    Notification::fake();

    for ($i = 0; $i < 15; $i++) {
        seedAlertCommandEval(['passed' => true]);
    }

    $exit = Artisan::call('shadow:alert');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('No alerts');

    Notification::assertNothingSent();
});

it('fires a notification when an alert is triggered', function (): void {
    Notification::fake();

    for ($i = 0; $i < 12; $i++) {
        seedAlertCommandEval(['passed' => false]);
    }

    $exit = Artisan::call('shadow:alert');

    expect($exit)->toBe(0);

    Notification::assertSentOnDemand(
        ShadowPassRateDroppedNotification::class,
        function (
            ShadowPassRateDroppedNotification $notification,
            array $channels,
            AnonymousNotifiable $notifiable,
        ): bool {
            return $notification->alert->agentClass === ShadowedEchoAgent::class
                && $notifiable->routeNotificationFor('mail') === 'ops@example.com';
        },
    );
});

it('marks the alert as deduped after firing', function (): void {
    Notification::fake();

    for ($i = 0; $i < 12; $i++) {
        seedAlertCommandEval(['passed' => false]);
    }

    Artisan::call('shadow:alert');
    Artisan::call('shadow:alert');

    Notification::assertSentOnDemandTimes(
        ShadowPassRateDroppedNotification::class,
        1,
    );
});

it('respects --agent filter', function (): void {
    Notification::fake();

    for ($i = 0; $i < 12; $i++) {
        seedAlertCommandEval([
            'agent_class' => ShadowedEchoAgent::class,
            'passed' => false,
        ]);
    }
    for ($i = 0; $i < 12; $i++) {
        seedAlertCommandEval([
            'agent_class' => EchoAgent::class,
            'passed' => false,
        ]);
    }

    $exit = Artisan::call('shadow:alert', [
        '--agent' => ShadowedEchoAgent::class,
    ]);

    expect($exit)->toBe(0);

    Notification::assertSentOnDemandTimes(
        ShadowPassRateDroppedNotification::class,
        1,
    );
});

it('does not send in --dry-run', function (): void {
    Notification::fake();

    for ($i = 0; $i < 12; $i++) {
        seedAlertCommandEval(['passed' => false]);
    }

    $exit = Artisan::call('shadow:alert', ['--dry-run' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('DRY RUN');

    Notification::assertNothingSent();
});

it('does not mark dedup in --dry-run', function (): void {
    Notification::fake();

    for ($i = 0; $i < 12; $i++) {
        seedAlertCommandEval(['passed' => false]);
    }

    Artisan::call('shadow:alert', ['--dry-run' => true]);
    Artisan::call('shadow:alert');

    Notification::assertSentOnDemandTimes(
        ShadowPassRateDroppedNotification::class,
        1,
    );
});

it('prints a readable alert line per agent', function (): void {
    Notification::fake();

    for ($i = 0; $i < 8; $i++) {
        seedAlertCommandEval(['passed' => true]);
    }
    for ($i = 0; $i < 4; $i++) {
        seedAlertCommandEval(['passed' => false]);
    }

    Artisan::call('shadow:alert');
    $output = Artisan::output();

    expect($output)->toContain('ALERT')
        ->and($output)->toContain(ShadowedEchoAgent::class)
        ->and($output)->toContain('%')
        ->and($output)->toContain('8/12');
});

it('handles multiple agents below threshold', function (): void {
    Notification::fake();

    for ($i = 0; $i < 12; $i++) {
        seedAlertCommandEval([
            'agent_class' => ShadowedEchoAgent::class,
            'passed' => false,
        ]);
    }
    for ($i = 0; $i < 12; $i++) {
        seedAlertCommandEval([
            'agent_class' => EchoAgent::class,
            'passed' => false,
        ]);
    }

    Artisan::call('shadow:alert');

    Notification::assertSentOnDemandTimes(
        ShadowPassRateDroppedNotification::class,
        2,
    );
});

it('routes mail to the configured address', function (): void {
    Notification::fake();
    enableAlerts(mailTo: 'oncall@example.com');

    for ($i = 0; $i < 12; $i++) {
        seedAlertCommandEval(['passed' => false]);
    }

    Artisan::call('shadow:alert');

    Notification::assertSentOnDemand(
        ShadowPassRateDroppedNotification::class,
        function (
            ShadowPassRateDroppedNotification $notification,
            array $channels,
            AnonymousNotifiable $notifiable,
        ): bool {
            return $notifiable->routeNotificationFor('mail') === 'oncall@example.com';
        },
    );
});
