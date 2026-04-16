<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Mosaiqo\Proofread\Clustering\FailureCluster;
use Mosaiqo\Proofread\Clustering\FailureClusterer;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Models\ShadowEval;
use Mosaiqo\Proofread\Shadow\DurationParser;
use Mosaiqo\Proofread\Similarity\SimilarityException;

/**
 * Group recent failures by semantic similarity so operators can spot systemic
 * patterns instead of reading failures case-by-case.
 */
final class ClusterFailuresCommand extends Command
{
    private const MAX_SIGNAL_LENGTH = 500;

    /**
     * @var string
     */
    protected $signature = 'evals:cluster
        {--source=eval_results : Source of failures: eval_results or shadow_evals}
        {--dataset= : Filter by dataset name (eval_results only)}
        {--agent= : Filter by agent FQCN (shadow_evals only)}
        {--since= : Only include failures newer than this duration (e.g. 1h, 24h, 7d)}
        {--threshold= : Minimum cosine similarity to join a cluster (default from config)}
        {--limit= : Maximum number of failures to process (default from config)}
        {--format=table : Output format: table or json}';

    /**
     * @var string
     */
    protected $description = 'Group failing eval results or shadow evals by semantic similarity.';

    public function handle(FailureClusterer $clusterer): int
    {
        $source = $this->resolveStringOption('source', 'eval_results');
        if (! in_array($source, ['eval_results', 'shadow_evals'], true)) {
            $this->error(sprintf('Unsupported --source value "%s". Use "eval_results" or "shadow_evals".', $source));

            return 2;
        }

        $format = $this->resolveStringOption('format', 'table');
        if (! in_array($format, ['table', 'json'], true)) {
            $this->error(sprintf('Unsupported --format value "%s". Use "table" or "json".', $format));

            return 2;
        }

        $threshold = $this->resolveThreshold();
        if ($threshold === null) {
            return 2;
        }

        $limit = $this->resolveLimit();
        if ($limit === null) {
            return 2;
        }

        $sinceRaw = $this->option('since');
        $sinceInput = is_string($sinceRaw) && $sinceRaw !== '' ? $sinceRaw : null;

        try {
            $since = $sinceInput !== null ? $this->parseSince($sinceInput) : null;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return 2;
        }

        $datasetRaw = $this->option('dataset');
        $dataset = is_string($datasetRaw) && $datasetRaw !== '' ? $datasetRaw : null;

        $agentRaw = $this->option('agent');
        $agent = is_string($agentRaw) && $agentRaw !== '' ? $agentRaw : null;

        $signals = $source === 'eval_results'
            ? $this->collectEvalResultSignals($dataset, $since, $limit)
            : $this->collectShadowEvalSignals($agent, $since, $limit);

        if ($signals === []) {
            $this->line('No failures found.');

            return 0;
        }

        try {
            $clusters = $clusterer->cluster($signals, threshold: $threshold);
        } catch (SimilarityException $exception) {
            $this->error('Failed to cluster failures: '.$exception->getMessage());

            return 1;
        }

        if ($format === 'json') {
            $this->line($this->renderJson($clusters));

            return 0;
        }

        $this->renderTable($signals, $clusters);

        return 0;
    }

    private function resolveStringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function resolveThreshold(): ?float
    {
        $raw = $this->option('threshold');
        if ($raw === null || $raw === '') {
            $default = config('proofread.clustering.default_threshold', 0.75);

            return is_numeric($default) ? (float) $default : 0.75;
        }

        if (! is_numeric($raw)) {
            $this->error(sprintf('The --threshold value must be numeric, got "%s".', is_scalar($raw) ? (string) $raw : 'non-scalar'));

            return null;
        }

        $value = (float) $raw;
        if ($value < -1.0 || $value > 1.0) {
            $this->error(sprintf('The --threshold value must be between -1 and 1, got %F.', $value));

            return null;
        }

        return $value;
    }

    private function resolveLimit(): ?int
    {
        $raw = $this->option('limit');
        if ($raw === null || $raw === '') {
            $default = config('proofread.clustering.default_limit', 500);

            return is_numeric($default) ? max(1, (int) $default) : 500;
        }

        if (! is_numeric($raw)) {
            $this->error(sprintf('The --limit value must be numeric, got "%s".', is_scalar($raw) ? (string) $raw : 'non-scalar'));

            return null;
        }

        $value = (int) $raw;
        if ($value < 1) {
            $this->error(sprintf('The --limit value must be >= 1, got %d.', $value));

            return null;
        }

        return $value;
    }

    private function parseSince(string $since): Carbon
    {
        try {
            $seconds = DurationParser::toSeconds($since);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException(
                sprintf('Unable to parse --since value "%s". Examples: 1h, 24h, 7d.', $since)
            );
        }

        return Carbon::now()->subSeconds($seconds);
    }

    /**
     * @return list<string>
     */
    private function collectEvalResultSignals(?string $dataset, ?Carbon $since, int $limit): array
    {
        $query = EvalResult::query()->where('passed', false);

        if ($dataset !== null) {
            $datasetIds = EvalDataset::query()
                ->where('name', $dataset)
                ->pluck('id')
                ->all();

            if ($datasetIds === []) {
                return [];
            }

            $query->whereIn('run_id', function (QueryBuilder $sub) use ($datasetIds): void {
                $sub->select('id')
                    ->from('eval_runs')
                    ->whereIn('dataset_id', $datasetIds);
            });
        }

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        /** @var list<EvalResult> $results */
        $results = $query
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->all();

        $signals = [];
        foreach ($results as $result) {
            $signals[] = $this->buildSignal(
                $this->inputToString($result->input),
                $result->output ?? '',
                $this->firstFailedReason($result->assertion_results),
            );
        }

        return $signals;
    }

    /**
     * @return list<string>
     */
    private function collectShadowEvalSignals(?string $agent, ?Carbon $since, int $limit): array
    {
        $query = ShadowEval::query()->where('passed', false);

        if ($agent !== null) {
            $query->where('agent_class', $agent);
        }

        if ($since !== null) {
            $query->where('evaluated_at', '>=', $since);
        }

        $query->with('capture');

        /** @var list<ShadowEval> $evals */
        $evals = $query
            ->orderBy('evaluated_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->all();

        $signals = [];
        foreach ($evals as $eval) {
            $capture = $eval->capture;
            if ($capture instanceof ShadowCapture) {
                $input = $this->inputToString($capture->input_payload);
                $output = $capture->output ?? '';
            } else {
                $input = '';
                $output = '';
            }
            $signals[] = $this->buildSignal(
                $input,
                $output,
                $this->firstFailedReason($eval->assertion_results),
            );
        }

        return $signals;
    }

    /**
     * @param  mixed  $input
     */
    private function inputToString($input): string
    {
        if (is_string($input)) {
            return $input;
        }

        if (is_array($input)) {
            $encoded = json_encode(
                $input,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            return $encoded === false ? '' : $encoded;
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>|null  $assertionResults
     */
    private function firstFailedReason(?array $assertionResults): string
    {
        if ($assertionResults === null || $assertionResults === []) {
            return '(no assertion reason captured)';
        }

        foreach ($assertionResults as $assertion) {
            $passed = $assertion['passed'] ?? null;
            if ($passed === false || $passed === 0 || $passed === '0') {
                $reason = $assertion['reason'] ?? null;
                if (is_string($reason) && $reason !== '') {
                    return $reason;
                }

                return '(assertion failed without reason)';
            }
        }

        return '(no failed assertion found)';
    }

    private function buildSignal(string $input, string $output, string $reason): string
    {
        $signal = sprintf("INPUT: %s\nOUTPUT: %s\nFAILED: %s", $input, $output, $reason);

        if (strlen($signal) > self::MAX_SIGNAL_LENGTH) {
            return substr($signal, 0, self::MAX_SIGNAL_LENGTH - 1).'…';
        }

        return $signal;
    }

    /**
     * @param  list<FailureCluster>  $clusters
     */
    private function renderJson(array $clusters): string
    {
        $payload = array_map(
            static fn (FailureCluster $cluster): array => [
                'representative' => $cluster->representative,
                'size' => $cluster->size(),
                'members' => array_map(
                    static fn (int $index, string $signal): array => [
                        'index' => $index,
                        'signal' => $signal,
                    ],
                    $cluster->memberIndexes,
                    $cluster->memberSignals,
                ),
            ],
            $clusters,
        );

        $encoded = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $encoded === false ? '[]' : $encoded;
    }

    /**
     * @param  list<string>  $signals
     * @param  list<FailureCluster>  $clusters
     */
    private function renderTable(array $signals, array $clusters): void
    {
        $this->line(sprintf('Found %d clusters from %d failures:', count($clusters), count($signals)));
        $this->line('');

        foreach ($clusters as $position => $cluster) {
            $number = $position + 1;
            $this->line(sprintf('[Cluster %d] — %d members', $number, $cluster->size()));
            $this->line('  representative: '.$this->oneLine($cluster->representative));
            $this->line('  members:');
            foreach ($cluster->memberIndexes as $offset => $memberIndex) {
                $signal = $cluster->memberSignals[$offset] ?? '';
                $this->line(sprintf('    #%d  %s', $memberIndex, $this->oneLine($signal)));
            }
            $this->line('');
        }
    }

    private function oneLine(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', $text);

        return is_string($collapsed) ? trim($collapsed) : trim($text);
    }
}
