<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use JsonException;
use Mosaiqo\Proofread\Proofread;
use RuntimeException;
use Throwable;

/**
 * Import a CSV or JSON file into a PHP dataset file usable by Proofread.
 *
 * CSV limitations:
 * - Cells are parsed as scalar strings; complex values (arrays, nested
 *   structures) cannot be expressed. Use JSON for those shapes.
 * - Meta keys are encoded via `meta_*` columns and flattened into a
 *   one-level `meta` array.
 *
 * JSON format:
 * - Top-level array of case objects.
 * - Each case must contain `input`. `expected` and `meta` are optional
 *   and can be arbitrarily nested.
 */
final class ImportDatasetCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'dataset:import
        {file : Path to a CSV or JSON file}
        {--name= : Override dataset name (defaults to the file basename)}
        {--output= : Destination PHP file (defaults to database/evals/{name}-dataset.php)}
        {--force : Overwrite the destination file if it already exists}';

    /**
     * @var string
     */
    protected $description = 'Import a CSV or JSON file into a Proofread dataset PHP file.';

    public function handle(): int
    {
        $fileArg = $this->argument('file');
        $path = is_string($fileArg) ? $fileArg : '';

        if ($path === '' || ! is_file($path)) {
            $this->error(sprintf('Input file not found: %s', $path));

            return 2;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension !== 'csv' && $extension !== 'json') {
            $this->error(sprintf(
                'Unsupported file extension ".%s". Supported: .csv, .json',
                $extension,
            ));

            return 2;
        }

        $nameOption = $this->option('name');
        $name = is_string($nameOption) && $nameOption !== ''
            ? $nameOption
            : pathinfo($path, PATHINFO_FILENAME);

        $outputOption = $this->option('output');
        $outputPath = is_string($outputOption) && $outputOption !== ''
            ? $outputOption
            : $this->laravel->basePath(sprintf('database/evals/%s-dataset.php', $name));

        $force = (bool) $this->option('force');

        if (file_exists($outputPath) && ! $force) {
            $this->error(sprintf(
                'Destination file already exists: %s (use --force to overwrite)',
                $outputPath,
            ));

            return 2;
        }

        try {
            $cases = $extension === 'csv'
                ? $this->parseCsv($path)
                : $this->parseJson($path);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return 2;
        }

        $rendered = $this->renderPhp($cases);

        try {
            Proofread::writeFile($outputPath, $rendered);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->line(sprintf('Imported %d case(s) as dataset "%s" into %s', count($cases), $name, $outputPath));

        return 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open CSV file "%s" for reading.', $path));
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new RuntimeException(sprintf('CSV file "%s" is empty.', $path));
            }

            /** @var list<string> $columns */
            $columns = array_map(static fn (mixed $v): string => is_string($v) ? $v : (string) $v, $header);

            if (! in_array('input', $columns, true)) {
                throw new RuntimeException(sprintf(
                    'CSV file "%s" is missing the required "input" column.',
                    $path,
                ));
            }

            $cases = [];
            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null]) {
                    continue;
                }
                $cases[] = $this->rowToCase($columns, $row);
            }

            return $cases;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string|null>  $row
     * @return array<string, mixed>
     */
    private function rowToCase(array $columns, array $row): array
    {
        $case = [];
        $meta = [];

        foreach ($columns as $index => $column) {
            $value = $row[$index] ?? null;

            if (str_starts_with($column, 'meta_')) {
                $metaKey = substr($column, 5);
                if ($metaKey !== '') {
                    $meta[$metaKey] = $value;
                }

                continue;
            }

            $case[$column] = $value;
        }

        if ($meta !== []) {
            $case['meta'] = $meta;
        }

        return $case;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseJson(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read JSON file "%s".', $path));
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(
                sprintf('Invalid JSON file "%s": %s', $path, $e->getMessage()),
            );
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new RuntimeException(
                sprintf('JSON file "%s" must contain a top-level array of case objects.', $path),
            );
        }

        $cases = [];
        foreach ($decoded as $index => $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException(
                    sprintf('Case at index %d in "%s" is not an object.', $index, $path),
                );
            }

            if (! array_key_exists('input', $entry)) {
                throw new RuntimeException(
                    sprintf('Case at index %d in "%s" is missing the "input" key.', $index, $path),
                );
            }

            /** @var array<string, mixed> $entry */
            $cases[] = $entry;
        }

        return $cases;
    }

    /**
     * @param  list<array<string, mixed>>  $cases
     */
    private function renderPhp(array $cases): string
    {
        $body = $this->exportArray($cases, 0);

        return "<?php\n\ndeclare(strict_types=1);\n\nreturn ".$body.";\n";
    }

    /**
     * @param  array<mixed>  $value
     */
    private function exportArray(array $value, int $depth): string
    {
        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth + 1);
        $closingIndent = str_repeat('    ', $depth);
        $isList = array_is_list($value);
        $lines = [];

        foreach ($value as $key => $item) {
            $rendered = $this->exportValue($item, $depth + 1);
            if ($isList) {
                $lines[] = $indent.$rendered.',';
            } else {
                $lines[] = $indent.$this->exportScalar($key).' => '.$rendered.',';
            }
        }

        return "[\n".implode("\n", $lines)."\n".$closingIndent.']';
    }

    private function exportValue(mixed $value, int $depth): string
    {
        if (is_array($value)) {
            return $this->exportArray($value, $depth);
        }

        return $this->exportScalar($value);
    }

    private function exportScalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return var_export($value, true);
        }

        if (is_string($value)) {
            return "'".strtr($value, ['\\' => '\\\\', "'" => "\\'"])."'";
        }

        return var_export($value, true);
    }
}
