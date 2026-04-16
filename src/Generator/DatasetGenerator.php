<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Generator;

use InvalidArgumentException;
use JsonException;
use Throwable;

final class DatasetGenerator
{
    public function __construct(
        private readonly string $defaultModel,
        private readonly int $maxRetries = 1,
    ) {
        if ($defaultModel === '') {
            throw new InvalidArgumentException('DatasetGenerator default model must not be empty.');
        }

        if ($maxRetries < 0) {
            throw new InvalidArgumentException(
                "DatasetGenerator maxRetries must be non-negative, got {$maxRetries}."
            );
        }
    }

    public function defaultModel(): string
    {
        return $this->defaultModel;
    }

    /**
     * Generate synthetic test cases using an LLM.
     *
     * @param  array<string, mixed>  $schema
     * @param  list<array<string, mixed>>|null  $seedCases
     * @return list<array<string, mixed>>
     *
     * @throws DatasetGeneratorException
     */
    public function generate(
        string $criteria,
        array $schema,
        int $count,
        ?string $model = null,
        ?array $seedCases = null,
    ): array {
        if ($count < 1) {
            throw new InvalidArgumentException("count must be at least 1, got {$count}.");
        }

        $effectiveModel = $model ?? $this->defaultModel;
        $prompt = $this->buildPrompt($criteria, $schema, $count, $seedCases);

        $attempts = 0;
        $maxAttempts = $this->maxRetries + 1;
        $lastRaw = '';
        $lastError = null;

        while ($attempts < $maxAttempts) {
            $raw = $this->invokeAgent($prompt, $effectiveModel);
            $lastRaw = $raw;

            try {
                return $this->parseCases($raw);
            } catch (InvalidArgumentException $exception) {
                $lastError = $exception->getMessage();
                $attempts++;
            }
        }

        throw new DatasetGeneratorException(
            sprintf(
                'DatasetGenerator failed to produce valid cases after %d attempts: %s',
                $maxAttempts,
                $lastError ?? 'unknown parse error',
            ),
            $lastRaw,
            $maxAttempts,
        );
    }

    private function invokeAgent(string $prompt, string $model): string
    {
        $agent = new DatasetGeneratorAgent;

        try {
            return $agent->prompt($prompt, model: $model)->text;
        } catch (Throwable $exception) {
            throw new DatasetGeneratorException(
                'DatasetGenerator invocation failed: '.$exception->getMessage(),
                '',
                1,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCases(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Empty generator response.');
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Generator response is not valid JSON: '.$exception->getMessage(),
            );
        }

        $cases = $this->extractList($decoded);

        if ($cases === null) {
            throw new InvalidArgumentException('Generator response is not a JSON array of cases.');
        }

        $normalized = [];
        foreach ($cases as $index => $case) {
            if (! is_array($case)) {
                throw new InvalidArgumentException(
                    sprintf('Case at index %d is not an object/array.', $index)
                );
            }

            if (! array_key_exists('input', $case)) {
                throw new InvalidArgumentException(
                    sprintf('Case at index %d is missing the "input" key.', $index)
                );
            }

            if (array_key_exists('meta', $case) && ! is_array($case['meta'])) {
                throw new InvalidArgumentException(
                    sprintf('Case at index %d has non-array "meta".', $index)
                );
            }

            /** @var array<string, mixed> $case */
            $normalized[] = $case;
        }

        return $normalized;
    }

    /**
     * @return list<mixed>|null
     */
    private function extractList(mixed $decoded): ?array
    {
        if (is_array($decoded) && array_is_list($decoded)) {
            return $decoded;
        }

        if (! is_array($decoded)) {
            return null;
        }

        foreach (['cases', 'data', 'items', 'results'] as $key) {
            $value = $decoded[$key] ?? null;
            if (is_array($value) && array_is_list($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<array<string, mixed>>|null  $seedCases
     */
    private function buildPrompt(
        string $criteria,
        array $schema,
        int $count,
        ?array $seedCases,
    ): string {
        $schemaJson = json_encode(
            $schema,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        $sections = [
            'You are generating synthetic test cases for evaluating an AI system.',
            '',
            'PURPOSE:',
            $criteria,
            '',
            'INPUT SCHEMA (JSON Schema):',
            $schemaJson === false ? '{}' : $schemaJson,
        ];

        if ($seedCases !== null && $seedCases !== []) {
            $seedJson = json_encode(
                $seedCases,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            $sections[] = '';
            $sections[] = 'EXAMPLE CASES (use these for style reference, do NOT repeat):';
            $sections[] = $seedJson === false ? '[]' : $seedJson;
        }

        $sections[] = '';
        $sections[] = sprintf(
            'Generate %d diverse, realistic test cases that conform to the input schema.',
            $count,
        );
        $sections[] = 'Mix typical cases with edge cases, long/short inputs, ambiguous cases, and adversarial variants.';
        $sections[] = '';
        $sections[] = 'Respond with ONLY a JSON array of cases, each shaped exactly like:';
        $sections[] = '{"input": <value matching schema>, "expected": <optional>, "meta": {"name": "<short unique name>"}}';
        $sections[] = '';
        $sections[] = 'No preamble, no commentary, no code fences.';

        return implode("\n", $sections);
    }
}
