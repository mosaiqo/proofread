<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Mosaiqo\Proofread\Shadow\Notifications\ShadowPassRateDroppedNotification;
use Mosaiqo\Proofread\Shadow\ShadowAlert;

function makeAlert(
    string $agentClass = 'App\\Agents\\BackendDevAgent',
    float $passRate = 0.7,
    float $threshold = 0.85,
    int $sampleSize = 20,
    int $passedCount = 14,
    int $failedCount = 6,
    ?DateTimeImmutable $windowFrom = null,
    ?DateTimeImmutable $windowTo = null,
): ShadowAlert {
    $windowFrom ??= new DateTimeImmutable('2026-04-15T12:00:00+00:00');
    $windowTo ??= new DateTimeImmutable('2026-04-15T13:00:00+00:00');

    return new ShadowAlert(
        agentClass: $agentClass,
        passRate: $passRate,
        threshold: $threshold,
        sampleSize: $sampleSize,
        passedCount: $passedCount,
        failedCount: $failedCount,
        windowFrom: $windowFrom,
        windowTo: $windowTo,
    );
}

it('sends a mail notification with the alert details', function (): void {
    Notification::fake();

    $alert = makeAlert();

    $notifiable = (new AnonymousNotifiable)->route('mail', 'ops@example.com');
    $notifiable->notify(new ShadowPassRateDroppedNotification($alert));

    Notification::assertSentTo(
        $notifiable,
        ShadowPassRateDroppedNotification::class,
        function (ShadowPassRateDroppedNotification $notification) use ($alert): bool {
            return $notification->alert->agentClass === $alert->agentClass;
        },
    );
});

it('sends via the configured channels', function (): void {
    /** @var ConfigRepository $config */
    $config = app('config');
    $config->set('proofread.shadow.alerts.channels', ['mail']);

    $notification = new ShadowPassRateDroppedNotification(makeAlert());
    $notifiable = new AnonymousNotifiable;

    expect($notification->via($notifiable))->toBe(['mail']);
});

it('includes agent_class passRate threshold in the array representation', function (): void {
    $alert = makeAlert(
        agentClass: 'App\\Agents\\BackendDevAgent',
        passRate: 0.75,
        threshold: 0.9,
    );

    $notification = new ShadowPassRateDroppedNotification($alert);
    $payload = $notification->toArray(new AnonymousNotifiable);

    expect($payload['agent_class'])->toBe('App\\Agents\\BackendDevAgent')
        ->and($payload['pass_rate'])->toBe(0.75)
        ->and($payload['threshold'])->toBe(0.9)
        ->and($payload['sample_size'])->toBe(20)
        ->and($payload['passed_count'])->toBe(14)
        ->and($payload['failed_count'])->toBe(6)
        ->and($payload['window_from'])->toBe('2026-04-15T12:00:00+00:00')
        ->and($payload['window_to'])->toBe('2026-04-15T13:00:00+00:00');
});

it('includes window boundaries in the mail body', function (): void {
    $alert = makeAlert();

    $mail = (new ShadowPassRateDroppedNotification($alert))->toMail(new AnonymousNotifiable);
    $rendered = renderMailLines($mail);

    expect($rendered)->toContain('2026-04-15T12:00:00+00:00')
        ->and($rendered)->toContain('2026-04-15T13:00:00+00:00');
});

it('includes sample size and pass/fail counts in the mail body', function (): void {
    $alert = makeAlert(sampleSize: 50, passedCount: 35, failedCount: 15);

    $mail = (new ShadowPassRateDroppedNotification($alert))->toMail(new AnonymousNotifiable);
    $rendered = renderMailLines($mail);

    expect($rendered)->toContain('50')
        ->and($rendered)->toContain('35')
        ->and($rendered)->toContain('15');
});

it('renders the subject with the agent class', function (): void {
    $alert = makeAlert(agentClass: 'App\\Agents\\BackendDevAgent');

    $mail = (new ShadowPassRateDroppedNotification($alert))->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toContain('App\\Agents\\BackendDevAgent');
});

/**
 * Flatten the MailMessage introLines + outroLines into a single string for
 * substring assertions without having to render the full Blade template.
 */
function renderMailLines(MailMessage $mail): string
{
    $lines = array_merge($mail->introLines, $mail->outroLines);

    $parts = [];
    foreach ($lines as $line) {
        $parts[] = is_string($line) ? $line : (string) $line;
    }

    return implode("\n", $parts);
}
