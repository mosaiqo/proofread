<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Agent;
use Mosaiqo\Proofread\Simulation\CostProjection;
use Mosaiqo\Proofread\Simulation\CostSimulationReport;
use Mosaiqo\Proofread\Simulation\CostSimulator;

/**
 * Simulate what an agent's historical production traffic would have cost
 * under alternative models. Analyzes shadow captures inside a time window
 * and produces a side-by-side cost comparison to help decide whether
 * switching models is worth it.
 */
final class SimulateCostCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'evals:cost-simulate
        {agent : FQCN of an Agent to simulate}
        {--days=30 : Number of days back from now to include}
        {--model=* : Limit the simulation to these alternative models (repeatable or comma-separated)}
        {--format=table : Output format: table or json}';

    /**
     * @var string
     */
    protected $description = 'Project the historical cost of an agent under alternative models using shadow captures.';

    public function handle(CostSimulator $simulator): int
    {
        $agentArgument = $this->argument('agent');
        $agent = is_string($agentArgument) ? $agentArgument : '';

        if ($agent === '' || ! class_exists($agent)) {
            $this->error(sprintf('Agent class "%s" not found.', $agent));

            return 2;
        }

        if (! is_a($agent, Agent::class, true)) {
            $this->error(sprintf(
                'Class "%s" must implement %s.',
                $agent,
                Agent::class,
            ));

            return 2;
        }

        $days = $this->parseDays();
        if ($days === null) {
            return 2;
        }

        $format = $this->parseFormat();
        if ($format === null) {
            return 2;
        }

        $alternatives = $this->parseModels();

        $to = Carbon::now();
        $from = (clone $to)->subDays($days);

        $report = $simulator->simulate($agent, $from, $to, $alternatives);

        if ($report->totalCaptures === 0) {
            $this->warn(sprintf(
                'No shadow captures found for %s in the last %d days. Enable shadow capture middleware to collect traffic data.',
                $agent,
                $days,
            ));

            return 0;
        }

        if ($format === 'json') {
            $this->line($this->renderJson($report, $days));

            return 0;
        }

        $this->renderTable($report, $days);

        return 0;
    }

    private function parseDays(): ?int
    {
        $raw = $this->option('days');
        if (is_int($raw)) {
            $value = $raw;
        } elseif (is_string($raw) && preg_match('/^\d+$/', $raw) === 1) {
            $value = (int) $raw;
        } else {
            $this->error(sprintf(
                '--days must be a positive integer, got "%s".',
                is_scalar($raw) ? (string) $raw : gettype($raw),
            ));

            return null;
        }

        if ($value < 1) {
            $this->error(sprintf('--days must be >= 1, got %d.', $value));

            return null;
        }

        return $value;
    }

    private function parseFormat(): ?string
    {
        $raw = $this->option('format');
        $format = is_string($raw) && $raw !== '' ? $raw : 'table';

        if (! in_array($format, ['table', 'json'], true)) {
            $this->error(sprintf(
                '--format must be "table" or "json", got "%s".',
                $format,
            ));

            return null;
        }

        return $format;
    }

    /**
     * @return list<string>
     */
    private function parseModels(): array
    {
        $raw = $this->option('model');
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $raw = [$raw];
        }

        if (! is_array($raw)) {
            return [];
        }

        $models = [];
        foreach ($raw as $entry) {
            if (! is_string($entry)) {
                continue;
            }
            foreach (explode(',', $entry) as $piece) {
                $trimmed = trim($piece);
                if ($trimmed !== '' && ! in_array($trimmed, $models, true)) {
                    $models[] = $trimmed;
                }
            }
        }

        return $models;
    }

    private function renderTable(CostSimulationReport $report, int $days): void
    {
        $this->line(sprintf('Cost simulation for %s', $report->agentClass));
        $this->line(sprintf(
            'Window: %s to %s (%d days)',
            $report->from->format('Y-m-d'),
            $report->to->format('Y-m-d'),
            $days,
        ));
        $this->line(sprintf('Captures: %s', number_format($report->totalCaptures)));
        $this->line('');

        $current = $report->current;
        $this->line(sprintf('Current: %s', $current->model === '' ? 'unknown' : $current->model));
        $this->line(sprintf('  Total cost:    %s', $this->formatCost($current->totalCost)));
        $this->line(sprintf('  Per capture:   %s', $this->formatCost($current->perCaptureCost)));
        $this->line(sprintf(
            '  Covered:       %d / %d captures',
            $current->coveredCaptures,
            $report->totalCaptures,
        ));

        if ($report->projections === []) {
            return;
        }

        $this->line('');
        $this->line('Alternatives:');

        $modelWidth = max(strlen('Model'), ...array_map(
            'strlen',
            array_keys($report->projections),
        ));
        $totalWidth = 10;
        $deltaWidth = 13;
        $perCapWidth = 12;
        $coverageWidth = 10;

        $header = sprintf(
            '  %s | %s | %s | %s | %s',
            str_pad('Model', $modelWidth),
            str_pad('Total', $totalWidth),
            str_pad('Delta', $deltaWidth),
            str_pad('Per capture', $perCapWidth),
            str_pad('Coverage', $coverageWidth),
        );
        $this->line($header);

        $sep = sprintf(
            '  %s | %s | %s | %s | %s',
            str_repeat('-', $modelWidth),
            str_repeat('-', $totalWidth),
            str_repeat('-', $deltaWidth),
            str_repeat('-', $perCapWidth),
            str_repeat('-', $coverageWidth),
        );
        $this->line($sep);

        $sorted = $report->projections;
        uasort(
            $sorted,
            static fn (CostProjection $a, CostProjection $b): int => $a->totalCost <=> $b->totalCost,
        );

        foreach ($sorted as $projection) {
            $delta = $projection->totalCost - $current->totalCost;
            $this->line(sprintf(
                '  %s | %s | %s | %s | %s',
                str_pad($projection->model, $modelWidth),
                str_pad($this->formatCost($projection->totalCost), $totalWidth),
                str_pad($this->formatDelta($delta), $deltaWidth),
                str_pad($this->formatCost($projection->perCaptureCost), $perCapWidth),
                str_pad(sprintf(
                    '%d/%d',
                    $projection->coveredCaptures,
                    $report->totalCaptures,
                ), $coverageWidth),
            ));
        }

        $cheapest = $report->cheapestAlternative();
        if ($cheapest !== null && $cheapest->totalCost < $current->totalCost) {
            $savings = $current->totalCost - $cheapest->totalCost;
            $pct = $current->totalCost > 0.0
                ? ($savings / $current->totalCost) * 100.0
                : 0.0;

            $this->line('');
            $this->line(sprintf(
                'Cheapest: %s - save %s (%.1f%% less)',
                $cheapest->model,
                $this->formatCost($savings),
                $pct,
            ));
        }
    }

    private function renderJson(CostSimulationReport $report, int $days): string
    {
        $projections = [];
        foreach ($report->projections as $projection) {
            $projections[] = [
                'model' => $projection->model,
                'total_cost' => $projection->totalCost,
                'delta' => round($projection->totalCost - $report->current->totalCost, 6),
                'per_capture_cost' => $projection->perCaptureCost,
                'covered_captures' => $projection->coveredCaptures,
                'skipped_captures' => $projection->skippedCaptures,
            ];
        }

        $cheapest = $report->cheapestAlternative();
        $cheapestPayload = null;
        if ($cheapest !== null) {
            $savings = $report->current->totalCost - $cheapest->totalCost;
            $pct = $report->current->totalCost > 0.0
                ? $savings / $report->current->totalCost
                : 0.0;
            $cheapestPayload = [
                'model' => $cheapest->model,
                'savings' => round($savings, 6),
                'savings_pct' => round($pct, 4),
            ];
        }

        $payload = [
            'agent_class' => $report->agentClass,
            'window' => [
                'from' => $report->from->format(DATE_ATOM),
                'to' => $report->to->format(DATE_ATOM),
                'days' => $days,
            ],
            'total_captures' => $report->totalCaptures,
            'current' => [
                'model' => $report->current->model,
                'total_cost' => $report->current->totalCost,
                'per_capture_cost' => $report->current->perCaptureCost,
                'covered_captures' => $report->current->coveredCaptures,
                'skipped_captures' => $report->current->skippedCaptures,
            ],
            'projections' => $projections,
            'cheapest_alternative' => $cheapestPayload,
        ];

        return (string) json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function formatCost(float $cost): string
    {
        return '$'.number_format($cost, 4, '.', '');
    }

    private function formatDelta(float $delta): string
    {
        $sign = $delta >= 0 ? '+' : '-';

        return sprintf('%s$%s', $sign, number_format(abs($delta), 4, '.', ''));
    }
}
