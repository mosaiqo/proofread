<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Mosaiqo\Proofread\Shadow\Notifications\ShadowPassRateDroppedNotification;
use Mosaiqo\Proofread\Shadow\ShadowAlert;
use Mosaiqo\Proofread\Shadow\ShadowAlertService;

/**
 * Evaluate the rolling shadow pass rate for one or all agents and dispatch a
 * ShadowPassRateDroppedNotification for each agent whose pass rate has dropped
 * below the configured threshold. Alerts are deduped via the cache so an agent
 * below threshold does not page on every scheduled run.
 *
 * Exit codes follow the convention that alerts are business signals, not
 * command failures: the command returns 0 whenever it ran cleanly (including
 * when it dispatched notifications), and a non-zero code only on internal
 * errors surfaced by the runtime.
 */
final class ShadowAlertCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'shadow:alert
        {--agent= : Filter by agent FQCN}
        {--dry-run : Evaluate and print alerts without dispatching notifications or marking dedup}';

    /**
     * @var string
     */
    protected $description = 'Check shadow pass rates against threshold and dispatch alerts for regressions.';

    public function handle(ShadowAlertService $service): int
    {
        if (config('proofread.shadow.alerts.enabled') !== true) {
            $this->warn('Shadow alerts are disabled. Set proofread.shadow.alerts.enabled=true to use this command.');

            return 0;
        }

        $agentOption = $this->option('agent');
        $agent = is_string($agentOption) && $agentOption !== '' ? $agentOption : null;
        $dryRun = (bool) $this->option('dry-run');

        $alerts = $service->check($agent);

        if ($alerts === []) {
            $this->line('No alerts - all agents within threshold.');

            return 0;
        }

        foreach ($alerts as $alert) {
            $this->line($this->formatAlertLine($alert));
        }

        if ($dryRun) {
            $this->line('DRY RUN - notifications not sent, dedup not marked.');

            return 0;
        }

        $mailTo = config('proofread.shadow.alerts.mail.to');
        $mailAddress = is_string($mailTo) && $mailTo !== '' ? $mailTo : null;

        foreach ($alerts as $alert) {
            $this->dispatchAlert($alert, $mailAddress);
            $service->markAlerted($alert);
        }

        return 0;
    }

    private function formatAlertLine(ShadowAlert $alert): string
    {
        $passRatePct = round($alert->passRate * 100, 1);
        $thresholdPct = round($alert->threshold * 100, 1);

        return sprintf(
            'ALERT %s: pass rate %s%% below threshold %s%% (%d/%d evals)',
            $alert->agentClass,
            $passRatePct,
            $thresholdPct,
            $alert->passedCount,
            $alert->sampleSize,
        );
    }

    private function dispatchAlert(ShadowAlert $alert, ?string $mailAddress): void
    {
        $notification = new ShadowPassRateDroppedNotification($alert);
        $route = Notification::route('mail', $mailAddress);
        $route->notify($notification);
    }
}
