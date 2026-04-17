<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalDatasetVersion;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * Diff two versions of a dataset.
 *
 * Accepted reference forms for the --base / --head options:
 *   - Short checksum (>= 6 hex chars) matched as prefix on the version checksum.
 *   - Run ULID (26 chars) — resolves to that run's dataset_version_id.
 *   - Literal "latest" — most recent version by first_seen_at.
 *   - Literal "previous" or "latest-1" — second-most-recent version by first_seen_at.
 *
 * Cases are indexed by meta.name when present, otherwise by positional
 * case_index. The comparison compares input + expected + meta (with the
 * indexing field stripped from meta when it was meta.name).
 */
final class DatasetDiffCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'evals:dataset:diff
        {dataset_name : Name of the dataset to diff}
        {--base= : Base version reference (short checksum, run ULID, "latest", "previous" / "latest-1")}
        {--head= : Head version reference (short checksum, run ULID, "latest", "previous" / "latest-1")}
        {--format=table : Output format: table or json}';

    /**
     * @var string
     */
    protected $description = 'Diff two dataset versions to see how cases were added, removed, or modified.';

    public function handle(): int
    {
        $datasetNameArg = $this->argument('dataset_name');
        $datasetName = is_string($datasetNameArg) ? $datasetNameArg : '';

        $dataset = EvalDataset::query()->where('name', $datasetName)->first();
        if ($dataset === null) {
            $this->error(sprintf('Dataset "%s" not found.', $datasetName));

            return 2;
        }

        /** @var list<EvalDatasetVersion> $versions */
        $versions = EvalDatasetVersion::query()
            ->where('eval_dataset_id', $dataset->id)
            ->orderBy('first_seen_at')
            ->orderBy('id')
            ->get()
            ->values()
            ->all();

        if ($versions === []) {
            $this->error(sprintf('Dataset "%s" has no versions recorded.', $datasetName));

            return 2;
        }

        if (count($versions) === 1) {
            $this->line(sprintf('Only one version exists for dataset "%s", nothing to diff.', $datasetName));

            return 0;
        }

        $baseRef = $this->option('base');
        $headRef = $this->option('head');
        $baseRefStr = is_string($baseRef) && $baseRef !== '' ? $baseRef : 'previous';
        $headRefStr = is_string($headRef) && $headRef !== '' ? $headRef : 'latest';

        $base = $this->resolveVersion($baseRefStr, $dataset, $versions, 'base');
        if ($base === null) {
            return 2;
        }

        $head = $this->resolveVersion($headRefStr, $dataset, $versions, 'head');
        if ($head === null) {
            return 2;
        }

        $format = $this->resolveFormat();
        if ($format === null) {
            return 2;
        }

        $diff = $this->computeDiff($base, $head);

        if ($format === 'json') {
            $this->line((string) json_encode(
                [
                    'dataset' => $dataset->name,
                    'base' => $this->versionHeader($base),
                    'head' => $this->versionHeader($head),
                    'summary' => $diff['summary'],
                    'changes' => $diff['changes'],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return 0;
        }

        $this->renderTable($dataset, $base, $head, $diff);

        return 0;
    }

    /**
     * @param  list<EvalDatasetVersion>  $versions
     */
    private function resolveVersion(
        string $ref,
        EvalDataset $dataset,
        array $versions,
        string $label,
    ): ?EvalDatasetVersion {
        if ($ref === 'latest') {
            return $versions[count($versions) - 1];
        }

        if ($ref === 'previous' || $ref === 'latest-1') {
            if (count($versions) < 2) {
                $this->error(sprintf('Cannot resolve %s="%s": fewer than two versions exist.', $label, $ref));

                return null;
            }

            return $versions[count($versions) - 2];
        }

        if ($this->looksLikeUlid($ref)) {
            /** @var EvalRun|null $run */
            $run = EvalRun::query()->where('id', $ref)->first();
            if ($run !== null && $run->dataset_version_id !== null) {
                /** @var EvalDatasetVersion|null $version */
                $version = EvalDatasetVersion::query()
                    ->where('id', $run->dataset_version_id)
                    ->where('eval_dataset_id', $dataset->id)
                    ->first();
                if ($version !== null) {
                    return $version;
                }
            }
        }

        if (strlen($ref) >= 6 && preg_match('/^[0-9a-f]+$/i', $ref) === 1) {
            /** @var EvalDatasetVersion|null $version */
            $version = EvalDatasetVersion::query()
                ->where('eval_dataset_id', $dataset->id)
                ->where('checksum', 'like', strtolower($ref).'%')
                ->orderByDesc('first_seen_at')
                ->first();
            if ($version !== null) {
                return $version;
            }
        }

        $this->error(sprintf('Could not resolve %s version from reference "%s".', $label, $ref));

        return null;
    }

    private function looksLikeUlid(string $ref): bool
    {
        return strlen($ref) === 26 && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $ref) === 1;
    }

    private function resolveFormat(): ?string
    {
        $format = $this->option('format');
        $format = is_string($format) ? $format : 'table';

        if ($format !== 'table' && $format !== 'json') {
            $this->error(sprintf('Unsupported --format value "%s". Use "table" or "json".', $format));

            return null;
        }

        return $format;
    }

    /**
     * @return array{summary: array{added: int, removed: int, modified: int, unchanged: int}, changes: list<array<string, mixed>>}
     */
    private function computeDiff(EvalDatasetVersion $base, EvalDatasetVersion $head): array
    {
        $baseIndex = $this->indexCases($base->cases);
        $headIndex = $this->indexCases($head->cases);

        $added = 0;
        $removed = 0;
        $modified = 0;
        $unchanged = 0;

        /** @var list<array<string, mixed>> $changes */
        $changes = [];

        $keys = array_unique(array_merge(array_keys($baseIndex), array_keys($headIndex)));
        sort($keys);

        foreach ($keys as $key) {
            $baseEntry = $baseIndex[$key] ?? null;
            $headEntry = $headIndex[$key] ?? null;

            if ($baseEntry === null && $headEntry !== null) {
                $added++;
                $changes[] = [
                    'status' => 'added',
                    'key' => $key,
                    'name' => $this->caseName($headEntry['case']),
                ];

                continue;
            }

            if ($headEntry === null && $baseEntry !== null) {
                $removed++;
                $changes[] = [
                    'status' => 'removed',
                    'key' => $key,
                    'name' => $this->caseName($baseEntry['case']),
                ];

                continue;
            }

            if ($baseEntry === null || $headEntry === null) {
                continue;
            }

            $baseContent = $this->comparableContent($baseEntry['case']);
            $headContent = $this->comparableContent($headEntry['case']);

            if ($baseContent === $headContent) {
                $unchanged++;

                continue;
            }

            $modified++;
            $changes[] = [
                'status' => 'modified',
                'key' => $key,
                'name' => $this->caseName($headEntry['case']),
                'diff' => $this->fieldDiff($baseContent, $headContent),
            ];
        }

        return [
            'summary' => [
                'added' => $added,
                'removed' => $removed,
                'modified' => $modified,
                'unchanged' => $unchanged,
            ],
            'changes' => $changes,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cases
     * @return array<string, array{case: array<string, mixed>, has_name: bool}>
     */
    private function indexCases(array $cases): array
    {
        $out = [];
        foreach ($cases as $position => $case) {
            $name = $this->caseName($case);
            $key = $name !== null ? $name : 'case_'.$position;
            $out[$key] = ['case' => $case, 'has_name' => $name !== null];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function caseName(array $case): ?string
    {
        $meta = $case['meta'] ?? null;
        if (is_array($meta)) {
            $name = $meta['name'] ?? null;
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $case
     * @return array{input: mixed, expected: mixed, meta: array<string, mixed>}
     */
    private function comparableContent(array $case): array
    {
        $meta = $case['meta'] ?? [];
        if (! is_array($meta)) {
            $meta = [];
        }
        unset($meta['name']);

        return [
            'input' => $case['input'] ?? null,
            'expected' => $case['expected'] ?? null,
            'meta' => $meta,
        ];
    }

    /**
     * @param  array{input: mixed, expected: mixed, meta: array<string, mixed>}  $base
     * @param  array{input: mixed, expected: mixed, meta: array<string, mixed>}  $head
     * @return array<string, array{base: mixed, head: mixed}>
     */
    private function fieldDiff(array $base, array $head): array
    {
        $fields = [];
        foreach (['input', 'expected', 'meta'] as $field) {
            if ($base[$field] !== $head[$field]) {
                $fields[$field] = [
                    'base' => $base[$field],
                    'head' => $head[$field],
                ];
            }
        }

        return $fields;
    }

    /**
     * @return array{version_id: string, checksum: string, case_count: int, first_seen_at: string}
     */
    private function versionHeader(EvalDatasetVersion $version): array
    {
        return [
            'version_id' => $version->id,
            'checksum' => $version->checksum,
            'case_count' => $version->case_count,
            'first_seen_at' => $version->first_seen_at->toIso8601String(),
        ];
    }

    /**
     * @param  array{summary: array{added: int, removed: int, modified: int, unchanged: int}, changes: list<array<string, mixed>>}  $diff
     */
    private function renderTable(
        EvalDataset $dataset,
        EvalDatasetVersion $base,
        EvalDatasetVersion $head,
        array $diff,
    ): void {
        $this->line(sprintf('Diff for dataset "%s":', $dataset->name));
        $this->line('  base: '.$this->formatVersionHeader($base));
        $this->line('  head: '.$this->formatVersionHeader($head));
        $this->line('');

        $summary = $diff['summary'];
        $this->line('Summary:');
        $this->line(sprintf('  Added:     %d', $summary['added']));
        $this->line(sprintf('  Removed:   %d', $summary['removed']));
        $this->line(sprintf('  Modified:  %d', $summary['modified']));
        $this->line(sprintf('  Unchanged: %d', $summary['unchanged']));

        if ($diff['changes'] === []) {
            $this->line('');
            $this->line('No changes detected.');

            return;
        }

        $this->line('');
        $this->line('Changes:');
        foreach ($diff['changes'] as $change) {
            $this->renderChange($change);
        }
    }

    private function formatVersionHeader(EvalDatasetVersion $version): string
    {
        return sprintf(
            '%s (checksum %s, %s, %d cases)',
            $version->id,
            substr($version->checksum, 0, 7),
            $version->first_seen_at->format('Y-m-d H:i:s'),
            $version->case_count,
        );
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function renderChange(array $change): void
    {
        $status = is_string($change['status'] ?? null) ? $change['status'] : 'unknown';
        $key = is_string($change['key'] ?? null) ? $change['key'] : '';
        $name = is_string($change['name'] ?? null) ? $change['name'] : null;

        $symbol = match ($status) {
            'added' => '[+]',
            'removed' => '[-]',
            'modified' => '[~]',
            default => '[?]',
        };

        $suffix = $name !== null ? sprintf(' "%s"', $name) : '';

        if ($status === 'modified' && isset($change['diff']) && is_array($change['diff'])) {
            $fields = array_keys($change['diff']);
            $hint = $fields !== [] ? sprintf('  (%s changed)', implode(', ', $fields)) : '';
            $this->line(sprintf('  %s %s%s%s', $symbol, $key, $suffix, $hint));

            return;
        }

        $this->line(sprintf('  %s %s%s', $symbol, $key, $suffix));
    }
}
