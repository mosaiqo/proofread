<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use InvalidArgumentException;
use JsonException;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\ObjectSchema;
use Mosaiqo\Proofread\Generator\DatasetGenerator;
use Mosaiqo\Proofread\Generator\DatasetGeneratorException;
use RuntimeException;
use Throwable;

final class GenerateDatasetCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'dataset:generate
        {--agent= : FQCN of an Agent implementing HasStructuredOutput to derive the schema from}
        {--schema= : Path to a JSON Schema file (mutually exclusive with --agent)}
        {--criteria= : Description of the dataset purpose (required)}
        {--count=10 : Number of cases to generate (1-100)}
        {--seed= : Path to a PHP file returning an array of seed cases (optional)}
        {--output= : Destination path; when set, writes to file (appends if it exists)}
        {--format=php : Output format: php or json}
        {--model= : Override the generator model}';

    /**
     * @var string
     */
    protected $description = 'Generate a synthetic dataset using an LLM.';

    public function handle(DatasetGenerator $generator): int
    {
        $agentOption = $this->option('agent');
        $agent = is_string($agentOption) && $agentOption !== '' ? $agentOption : null;

        $schemaOption = $this->option('schema');
        $schemaPath = is_string($schemaOption) && $schemaOption !== '' ? $schemaOption : null;

        $criteriaOption = $this->option('criteria');
        $criteria = is_string($criteriaOption) && $criteriaOption !== '' ? $criteriaOption : null;

        $countOption = $this->option('count');
        $count = is_numeric($countOption) ? (int) $countOption : 10;

        $seedOption = $this->option('seed');
        $seedPath = is_string($seedOption) && $seedOption !== '' ? $seedOption : null;

        $outputOption = $this->option('output');
        $outputPath = is_string($outputOption) && $outputOption !== '' ? $outputOption : null;

        $formatOption = $this->option('format');
        $format = is_string($formatOption) && $formatOption !== '' ? $formatOption : 'php';

        $modelOption = $this->option('model');
        $model = is_string($modelOption) && $modelOption !== '' ? $modelOption : null;

        if ($agent === null && $schemaPath === null) {
            $this->error('Either --agent or --schema is required.');

            return 2;
        }

        if ($agent !== null && $schemaPath !== null) {
            $this->error('--agent and --schema are mutually exclusive.');

            return 2;
        }

        if ($criteria === null) {
            $this->error('The --criteria option is required.');

            return 2;
        }

        if ($count < 1 || $count > 100) {
            $this->error(sprintf('The --count value must be between 1 and 100, got %d.', $count));

            return 2;
        }

        if (! in_array($format, ['php', 'json'], true)) {
            $this->error(sprintf('The --format value must be "php" or "json", got "%s".', $format));

            return 2;
        }

        try {
            $schema = $agent !== null
                ? $this->schemaFromAgent($agent)
                : $this->schemaFromFile((string) $schemaPath);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 2;
        }

        try {
            $seedCases = $seedPath !== null ? $this->loadSeedCases($seedPath) : null;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 2;
        }

        try {
            $cases = $generator->generate(
                criteria: $criteria,
                schema: $schema,
                count: $count,
                model: $model,
                seedCases: $seedCases,
            );
        } catch (DatasetGeneratorException $e) {
            $this->error('Dataset generation failed: '.$e->getMessage());

            return 1;
        } catch (Throwable $e) {
            $this->error('Unexpected error: '.$e->getMessage());

            return 1;
        }

        $merged = $cases;
        if ($outputPath !== null && is_file($outputPath) && $format === 'php') {
            try {
                $existing = $this->loadExistingPhpArray($outputPath);
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return 1;
            }

            $merged = array_merge($existing, $cases);
        }

        $rendered = $format === 'json'
            ? $this->renderJson($merged)
            : $this->renderPhp($merged);

        if ($outputPath === null) {
            $this->line($rendered);

            return 0;
        }

        try {
            $this->writeAtomic($outputPath, $rendered);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->line(sprintf('Wrote %d case(s) to %s', count($cases), $outputPath));

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaFromAgent(string $agentClass): array
    {
        if (! class_exists($agentClass)) {
            throw new InvalidArgumentException(
                sprintf('Agent class [%s] does not exist.', $agentClass)
            );
        }

        if (! is_a($agentClass, HasStructuredOutput::class, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Agent class [%s] does not implement [%s].',
                    $agentClass,
                    HasStructuredOutput::class,
                )
            );
        }

        /** @var HasStructuredOutput $agent */
        $agent = app($agentClass);
        $properties = $agent->schema(new JsonSchemaTypeFactory);

        return (new ObjectSchema($properties))->toSchema();
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaFromFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException(
                sprintf('Schema file not found or unreadable: %s', $path)
            );
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException(
                sprintf('Unable to read schema file: %s', $path)
            );
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                sprintf('Invalid JSON schema file "%s": %s', $path, $e->getMessage()),
            );
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(
                sprintf('Schema file "%s" must contain a JSON object.', $path)
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadSeedCases(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException(
                sprintf('Seed file not found or unreadable: %s', $path)
            );
        }

        /** @var mixed $loaded */
        $loaded = require $path;

        if (! is_array($loaded)) {
            throw new InvalidArgumentException(
                sprintf('Seed file "%s" must return an array.', $path)
            );
        }

        if (! array_is_list($loaded)) {
            throw new InvalidArgumentException(
                sprintf('Seed file "%s" must return an indexed list of cases.', $path)
            );
        }

        $normalized = [];
        foreach ($loaded as $index => $case) {
            if (! is_array($case)) {
                throw new InvalidArgumentException(
                    sprintf('Seed case at index %d is not an array.', $index)
                );
            }
            /** @var array<string, mixed> $case */
            $normalized[] = $case;
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadExistingPhpArray(string $path): array
    {
        /** @var mixed $loaded */
        $loaded = require $path;

        if (! is_array($loaded) || ! array_is_list($loaded)) {
            throw new RuntimeException(
                sprintf('Existing output file "%s" must return an indexed list of cases.', $path)
            );
        }

        $normalized = [];
        foreach ($loaded as $index => $case) {
            if (! is_array($case)) {
                throw new RuntimeException(
                    sprintf('Existing case at index %d in "%s" is not an array.', $index, $path)
                );
            }
            /** @var array<string, mixed> $case */
            $normalized[] = $case;
        }

        return $normalized;
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

    private function writeAtomic(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            $created = $this->silently(static fn (): bool => mkdir($directory, 0755, true));
            if (! $created && ! is_dir($directory)) {
                throw new RuntimeException(sprintf('Unable to create output directory "%s".', $directory));
            }
        }

        if (! is_writable($directory)) {
            throw new RuntimeException(sprintf('Output directory "%s" is not writable.', $directory));
        }

        $tmpPath = $path.'.'.getmypid().'.tmp';

        $bytes = $this->silently(static fn (): int|false => file_put_contents($tmpPath, $contents));
        if ($bytes === false) {
            throw new RuntimeException(sprintf('Unable to write temporary file "%s".', $tmpPath));
        }

        $renamed = $this->silently(static fn (): bool => rename($tmpPath, $path));
        if (! $renamed) {
            $this->silently(static fn (): bool => unlink($tmpPath));
            throw new RuntimeException(sprintf('Unable to move generated dataset to "%s".', $path));
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    private function silently(callable $fn): mixed
    {
        set_error_handler(static fn (): bool => true);
        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }
}
