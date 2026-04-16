<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Shadow;

use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use Mosaiqo\Proofread\Models\ShadowEval;

/**
 * Detects agents whose rolling shadow pass rate has dropped below a configured
 * threshold and produces dedup-aware ShadowAlert snapshots for the consumer
 * (typically the shadow:alert Artisan command) to dispatch.
 */
final class ShadowAlertService
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly float $threshold,
        private readonly string $window,
        private readonly int $minSampleSize,
        private readonly string $dedupWindow,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(CacheRepository $cache, array $config): self
    {
        $threshold = $config['pass_rate_threshold'] ?? 0.85;
        $window = $config['window'] ?? '1h';
        $minSamples = $config['min_sample_size'] ?? 10;
        $dedupWindow = $config['dedup_window'] ?? '1h';

        return new self(
            cache: $cache,
            threshold: is_numeric($threshold) ? (float) $threshold : 0.85,
            window: is_string($window) && $window !== '' ? $window : '1h',
            minSampleSize: is_numeric($minSamples) ? (int) $minSamples : 10,
            dedupWindow: is_string($dedupWindow) && $dedupWindow !== '' ? $dedupWindow : '1h',
        );
    }

    /**
     * @return list<ShadowAlert>
     */
    public function check(?string $agentClass = null): array
    {
        $to = Carbon::now();
        $from = $to->copy()->subSeconds(DurationParser::toSeconds($this->window));

        $agents = $agentClass !== null
            ? [$agentClass]
            : $this->discoverAgents($from, $to);

        $alerts = [];
        foreach ($agents as $agent) {
            $alert = $this->evaluateAgent($agent, $from, $to);
            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    public function markAlerted(ShadowAlert $alert): void
    {
        $this->cache->put(
            $this->dedupKey($alert->agentClass, $alert->threshold),
            true,
            DurationParser::toSeconds($this->dedupWindow),
        );
    }

    /**
     * @return list<string>
     */
    private function discoverAgents(Carbon $from, Carbon $to): array
    {
        /** @var list<string> $classes */
        $classes = ShadowEval::query()
            ->where('evaluated_at', '>=', $from)
            ->where('evaluated_at', '<=', $to)
            ->select('agent_class')
            ->distinct()
            ->pluck('agent_class')
            ->all();

        return $classes;
    }

    private function evaluateAgent(string $agentClass, Carbon $from, Carbon $to): ?ShadowAlert
    {
        $total = ShadowEval::query()
            ->where('agent_class', $agentClass)
            ->where('evaluated_at', '>=', $from)
            ->where('evaluated_at', '<=', $to)
            ->count();

        if ($total < $this->minSampleSize) {
            return null;
        }

        $passed = ShadowEval::query()
            ->where('agent_class', $agentClass)
            ->where('evaluated_at', '>=', $from)
            ->where('evaluated_at', '<=', $to)
            ->where('passed', true)
            ->count();

        $passRate = $total === 0 ? 1.0 : $passed / $total;

        if ($passRate >= $this->threshold) {
            return null;
        }

        if ($this->cache->get($this->dedupKey($agentClass, $this->threshold)) !== null) {
            return null;
        }

        return new ShadowAlert(
            agentClass: $agentClass,
            passRate: $passRate,
            threshold: $this->threshold,
            sampleSize: $total,
            passedCount: $passed,
            failedCount: $total - $passed,
            windowFrom: DateTimeImmutable::createFromInterface($from),
            windowTo: DateTimeImmutable::createFromInterface($to),
        );
    }

    private function dedupKey(string $agentClass, float $threshold): string
    {
        return sprintf('proofread:shadow:alert:%s:%s', $agentClass, (string) $threshold);
    }
}
