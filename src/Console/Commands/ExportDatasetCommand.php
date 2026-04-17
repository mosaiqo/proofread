<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalDatasetVersion;
use Mosaiqo\Proofread\Proofread;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Export a persisted dataset version as JSON or CSV.
 *
 * Version resolution (in order):
 * - `--version=latest` (default) → most recent version by first_seen_at.
 * - `--version=<short-checksum>` → prefix match (>= 6 hex chars) against
 *   `eval_dataset_versions.checksum`.
 */
final class ExportDatasetCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'dataset:export
        {dataset : Name of the dataset to export}
        {--format=csv : Output format: csv or json}
        {--output= : Write the export to this path instead of stdout}
        {--dataset-version=latest : Version identifier (checksum prefix or "latest")}';

    /**
     * @var string
     */
    protected $description = 'Export a persisted dataset version as JSON or CSV.';

    public function handle(): int
    {
        $datasetArg = $this->argument('dataset');
        $datasetName = is_string($datasetArg) ? $datasetArg : '';

        $dataset = EvalDataset::query()->where('name', $datasetName)->first();
        if ($dataset === null) {
            $this->error(sprintf('Dataset "%s" not found.', $datasetName));

            return 2;
        }

        $format = $this->option('format');
        $format = is_string($format) ? strtolower($format) : 'csv';
        if ($format !== 'csv' && $format !== 'json') {
            $this->error(sprintf('Unsupported --format value "%s". Use "csv" or "json".', $format));

            return 2;
        }

        $versionOption = $this->option('dataset-version');
        $versionRef = is_string($versionOption) && $versionOption !== '' ? $versionOption : 'latest';

        $version = $this->resolveVersion($versionRef, $dataset->id);
        if ($version === null) {
            $this->error(sprintf(
                'Could not resolve version "%s" for dataset "%s".',
                $versionRef,
                $datasetName,
            ));

            return 2;
        }

        $cases = $version->cases;
        $rendered = $format === 'json'
            ? $this->renderJson($cases)
            : $this->renderCsv($cases);

        $outputOption = $this->option('output');
        $outputPath = is_string($outputOption) && $outputOption !== '' ? $outputOption : null;

        if ($outputPath !== null) {
            try {
                Proofread::writeFile($outputPath, $rendered);
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return 1;
            }
            $this->line(sprintf('Exported %d case(s) to %s', count($cases), $outputPath));

            return 0;
        }

        $this->output->writeln($rendered, OutputInterface::OUTPUT_RAW);

        return 0;
    }

    private function resolveVersion(string $ref, string $datasetId): ?EvalDatasetVersion
    {
        if ($ref === 'latest') {
            /** @var EvalDatasetVersion|null $version */
            $version = EvalDatasetVersion::query()
                ->where('eval_dataset_id', $datasetId)
                ->orderByDesc('first_seen_at')
                ->orderByDesc('id')
                ->first();

            return $version;
        }

        if (strlen($ref) >= 6 && preg_match('/^[0-9a-f]+$/i', $ref) === 1) {
            /** @var EvalDatasetVersion|null $version */
            $version = EvalDatasetVersion::query()
                ->where('eval_dataset_id', $datasetId)
                ->where('checksum', 'like', strtolower($ref).'%')
                ->orderByDesc('first_seen_at')
                ->first();

            return $version;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $cases
     */
    private function renderJson(array $cases): string
    {
        $encoded = json_encode(
            $cases,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $encoded === false ? '[]' : $encoded;
    }

    /**
     * @param  list<array<string, mixed>>  $cases
     */
    private function renderCsv(array $cases): string
    {
        if ($cases === []) {
            return "\n";
        }

        $rows = [];
        $allColumns = [];

        foreach ($cases as $case) {
            $row = [];
            foreach ($case as $key => $value) {
                if ($key === 'meta' && is_array($value)) {
                    foreach ($value as $metaKey => $metaValue) {
                        $column = 'meta_'.(string) $metaKey;
                        $row[$column] = $this->toCsvCell($metaValue);
                        $allColumns[$column] = true;
                    }

                    continue;
                }

                $column = (string) $key;
                $row[$column] = $this->toCsvCell($value);
                $allColumns[$column] = true;
            }
            $rows[] = $row;
        }

        $columns = array_keys($allColumns);
        $ordered = $this->orderColumns($columns);

        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            throw new RuntimeException('Unable to open temporary CSV buffer.');
        }

        fputcsv($out, $ordered);
        foreach ($rows as $row) {
            $line = [];
            foreach ($ordered as $column) {
                $line[] = $row[$column] ?? '';
            }
            fputcsv($out, $line);
        }

        rewind($out);
        $contents = stream_get_contents($out);
        fclose($out);

        return $contents === false ? '' : $contents;
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function orderColumns(array $columns): array
    {
        $priority = ['input', 'expected'];

        $ordered = [];
        foreach ($priority as $key) {
            if (in_array($key, $columns, true)) {
                $ordered[] = $key;
            }
        }

        foreach ($columns as $column) {
            if (! in_array($column, $ordered, true)) {
                $ordered[] = $column;
            }
        }

        return $ordered;
    }

    private function toCsvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '' : $encoded;
    }
}
