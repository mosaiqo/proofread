<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Shadow\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Mosaiqo\Proofread\Shadow\ShadowAlert;

/**
 * Notification dispatched when an agent's rolling shadow pass rate has dropped
 * below the configured threshold. Channels are driven by the
 * proofread.shadow.alerts.channels config array; only `mail` and the default
 * `toArray` broadcast/database shape ship with the package. Additional
 * channels (e.g. Slack) can be wired by the consumer by installing the
 * relevant notification channel package and extending this class.
 */
class ShadowPassRateDroppedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ShadowAlert $alert) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        /** @var mixed $channels */
        $channels = config('proofread.shadow.alerts.channels', ['mail']);

        if (! is_array($channels)) {
            return ['mail'];
        }

        $result = [];
        foreach ($channels as $channel) {
            if (is_string($channel) && $channel !== '') {
                $result[] = $channel;
            }
        }

        return $result === [] ? ['mail'] : $result;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $passRatePct = round($this->alert->passRate * 100, 1);
        $thresholdPct = round($this->alert->threshold * 100, 1);

        return (new MailMessage)
            ->subject(sprintf('Proofread: pass rate dropped for %s', $this->alert->agentClass))
            ->line(sprintf(
                'Pass rate for %s dropped to %s%%.',
                $this->alert->agentClass,
                $passRatePct,
            ))
            ->line(sprintf('Threshold: %s%%.', $thresholdPct))
            ->line(sprintf(
                'Sample size: %d evaluations (%d passed, %d failed).',
                $this->alert->sampleSize,
                $this->alert->passedCount,
                $this->alert->failedCount,
            ))
            ->line(sprintf(
                'Window: %s to %s.',
                $this->alert->windowFrom->format('c'),
                $this->alert->windowTo->format('c'),
            ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'agent_class' => $this->alert->agentClass,
            'pass_rate' => $this->alert->passRate,
            'threshold' => $this->alert->threshold,
            'sample_size' => $this->alert->sampleSize,
            'passed_count' => $this->alert->passedCount,
            'failed_count' => $this->alert->failedCount,
            'window_from' => $this->alert->windowFrom->format('c'),
            'window_to' => $this->alert->windowTo->format('c'),
        ];
    }
}
