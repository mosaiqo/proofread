<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;
use Mosaiqo\Proofread\Clustering\FailureCluster;
use Mosaiqo\Proofread\Coverage\CaseCoverage;
use Mosaiqo\Proofread\Coverage\CoverageAnalyzer;
use Mosaiqo\Proofread\Coverage\CoverageReport;
use Mosaiqo\Proofread\Models\EvalDataset;

/**
 * Surface which production captures the dataset does not cover. Embed
 * every dataset case and every recent capture, match each capture to its
 * nearest case by cosine similarity, report the gap, and cluster the
 * uncovered captures so the next dataset revision has obvious targets.
 */
final class CoverageCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'evals:coverage
        {agent : FQCN of an Agent to analyze}
        {dataset : Name of the EvalDataset to compare against}
        {--days=30 : Number of days back from now to include}
        {--threshold=0.7 : Minimum cosine similarity to consider a capture covered}
        {--max-captures=500 : Maximum number of captures to analyze}
        {--embedding-model= : Override the embedding model}
        {--format=table : Output format: table or json}';

    /**
     * @var string
     */
    protected $description = 'Analyze which shadow captures are not covered by a dataset using embedding similarity.';

    public function handle(CoverageAnalyzer $analyzer): int
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

        $datasetArgument = $this->argument('dataset');
        $datasetName = is_string($datasetArgument) ? $datasetArgument : '';

        if ($datasetName === '' || ! EvalDataset::query()->where('name', $datasetName)->exists()) {
            $this->error(sprintf('Dataset "%s" does not exist.', $datasetName));

            return 2;
        }

        $days = $this->parseDays();
        if ($days === null) {
            return 2;
        }

        $threshold = $this->parseThreshold();
        if ($threshold === null) {
            return 2;
        }

        $maxCaptures = $this->parseMaxCaptures();
        if ($maxCaptures === null) {
            return 2;
        }

        $format = $this->parseFormat();
        if ($format === null) {
            return 2;
        }

        $embeddingModelRaw = $this->option('embedding-model');
        $embeddingModel = is_string($embeddingModelRaw) && $embeddingModelRaw !== ''
            ? $embeddingModelRaw
            : null;

        $to = Carbon::now();
        $from = (clone $to)->subDays($days);

        try {
            $report = $analyzer->analyze(
                agentClass: $agent,
                datasetName: $datasetName,
                from: $from,
                to: $to,
                threshold: $threshold,
                maxCaptures: $maxCaptures,
                model: $embeddingModel,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return 2;
        }

        if ($report->totalCaptures === 0) {
            $this->warn(sprintf(
                'No captures found for %s in the last %d days.',
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

    private function parseThreshold(): ?float
    {
        $raw = $this->option('threshold');
        if (! is_numeric($raw)) {
            $this->error(sprintf(
                '--threshold must be numeric, got "%s".',
                is_scalar($raw) ? (string) $raw : 'non-scalar',
            ));

            return null;
        }

        $value = (float) $raw;
        if ($value < 0.0 || $value > 1.0) {
            $this->error(sprintf('--threshold must be between 0 and 1, got %F.', $value));

            return null;
        }

        return $value;
    }

    private function parseMaxCaptures(): ?int
    {
        $raw = $this->option('max-captures');
        if (is_int($raw)) {
            $value = $raw;
        } elseif (is_string($raw) && preg_match('/^\d+$/', $raw) === 1) {
            $value = (int) $raw;
        } else {
            $this->error(sprintf(
                '--max-captures must be a positive integer, got "%s".',
                is_scalar($raw) ? (string) $raw : gettype($raw),
            ));

            return null;
        }

        if ($value < 1) {
            $this->error(sprintf('--max-captures must be >= 1, got %d.', $value));

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

    private function renderTable(CoverageReport $report, int $days): void
    {
        $this->line(sprintf(
            'Coverage report for %s vs dataset "%s"',
            $report->agentClass,
            $report->datasetName,
        ));
        $this->line(sprintf(
            'Window: %s to %s (%d days)',
            $report->from->format('Y-m-d'),
            $report->to->format('Y-m-d'),
            $days,
        ));
        $this->line(sprintf(
            'Threshold: %s cosine similarity',
            number_format($report->threshold, 2, '.', ''),
        ));
        $this->line('');

        $this->line('Summary:');
        $this->line(sprintf('  Total captures:    %d', $report->totalCaptures));

        $ratioPct = $report->coverageRatio() * 100.0;
        $this->line(sprintf(
            '  Covered:           %d (%.1f%%)',
            $report->coveredCount,
            $ratioPct,
        ));
        $this->line(sprintf('  Uncovered:         %d', $report->uncoveredCount));
        if ($report->skippedCount > 0) {
            $this->line(sprintf(
                '  Skipped:           %d (no usable input)',
                $report->skippedCount,
            ));
        }

        if ($report->caseCoverage !== []) {
            $this->line('');
            $this->line('Case coverage:');

            $caseWidth = max(4, strlen((string) (count($report->caseCoverage) - 1)));
            $nameWidth = max(
                strlen('Name'),
                ...array_map(
                    static fn (CaseCoverage $c): int => strlen($c->caseName ?? '(unnamed)'),
                    $report->caseCoverage,
                ),
            );
            $matchedWidth = max(strlen('Matched'), 7);

            $this->line(sprintf(
                '  %s | %s | %s | %s',
                str_pad('Case', $caseWidth),
                str_pad('Name', $nameWidth),
                str_pad('Matched', $matchedWidth),
                'Avg similarity',
            ));
            $this->line(sprintf(
                '  %s | %s | %s | %s',
                str_repeat('-', $caseWidth),
                str_repeat('-', $nameWidth),
                str_repeat('-', $matchedWidth),
                '--------------',
            ));

            foreach ($report->caseCoverage as $cc) {
                $this->line(sprintf(
                    '  %s | %s | %s | %.2f',
                    str_pad((string) $cc->caseIndex, $caseWidth),
                    str_pad($cc->caseName ?? '(unnamed)', $nameWidth),
                    str_pad((string) $cc->matchedCaptures, $matchedWidth),
                    $cc->avgSimilarity,
                ));
            }
        }

        if ($report->uncoveredClusters !== []) {
            $this->line('');
            $this->line(sprintf('Uncovered clusters (%d):', count($report->uncoveredClusters)));
            $this->line('');

            foreach ($report->uncoveredClusters as $position => $cluster) {
                $number = $position + 1;
                $this->line(sprintf(
                    '[Cluster %d] %d captures, representative:',
                    $number,
                    $cluster->size(),
                ));
                $this->line('  '.$this->oneLine($cluster->representative));
                $this->line('');
            }

            $recommendation = $this->buildRecommendation($report->uncoveredClusters);
            if ($recommendation !== null) {
                $this->line($recommendation);
            }
        }
    }

    private function renderJson(CoverageReport $report, int $days): string
    {
        $caseCoverage = [];
        foreach ($report->caseCoverage as $cc) {
            $caseCoverage[] = [
                'case_index' => $cc->caseIndex,
                'name' => $cc->caseName,
                'matched_captures' => $cc->matchedCaptures,
                'avg_similarity' => round($cc->avgSimilarity, 6),
            ];
        }

        $uncovered = [];
        foreach ($report->uncovered as $u) {
            $uncovered[] = [
                'capture_id' => $u->captureId,
                'input_snippet' => $u->inputSnippet,
                'max_similarity' => round($u->maxSimilarity, 6),
                'nearest_case_index' => $u->nearestCaseIndex,
            ];
        }

        $clusters = [];
        foreach ($report->uncoveredClusters as $cluster) {
            $clusters[] = [
                'representative' => $cluster->representative,
                'size' => $cluster->size(),
                'member_indexes' => $cluster->memberIndexes,
            ];
        }

        $payload = [
            'agent_class' => $report->agentClass,
            'dataset_name' => $report->datasetName,
            'window' => [
                'from' => $report->from->format(DATE_ATOM),
                'to' => $report->to->format(DATE_ATOM),
                'days' => $days,
            ],
            'threshold' => $report->threshold,
            'total_captures' => $report->totalCaptures,
            'covered_count' => $report->coveredCount,
            'uncovered_count' => $report->uncoveredCount,
            'skipped_count' => $report->skippedCount,
            'coverage_ratio' => round($report->coverageRatio(), 6),
            'case_coverage' => $caseCoverage,
            'uncovered' => $uncovered,
            'uncovered_clusters' => $clusters,
        ];

        return (string) json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @param  list<FailureCluster>  $clusters
     */
    private function buildRecommendation(array $clusters): ?string
    {
        $topics = [];
        foreach (array_slice($clusters, 0, 3) as $cluster) {
            $topic = $this->extractTopic($cluster->representative);
            if ($topic !== null && ! in_array($topic, $topics, true)) {
                $topics[] = $topic;
            }
        }

        if ($topics === []) {
            return null;
        }

        return sprintf(
            'Recommendation: add dataset cases covering %s.',
            implode('; ', $topics),
        );
    }

    private function extractTopic(string $representative): ?string
    {
        $lower = strtolower($this->oneLine($representative));
        $stripped = trim(preg_replace('/[^a-z0-9\s]+/', ' ', $lower) ?? '');
        if ($stripped === '') {
            return null;
        }

        $words = array_values(array_filter(
            preg_split('/\s+/', $stripped) ?: [],
            static fn (string $w): bool => $w !== '',
        ));
        if ($words === []) {
            return null;
        }

        $take = array_slice($words, 0, min(8, count($words)));

        return implode(' ', array_slice($take, 0, max(3, count($take))));
    }

    private function oneLine(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', $text);

        return is_string($collapsed) ? trim($collapsed) : trim($text);
    }
}
