<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Generator\DatasetGenerator;
use Mosaiqo\Proofread\Generator\DatasetGeneratorAgent;
use Mosaiqo\Proofread\Generator\DatasetGeneratorException;

beforeEach(function (): void {
    config()->set('ai.default', 'openai');
});

it('generates a dataset of N cases from a schema', function (): void {
    $payload = json_encode([
        ['input' => 'a', 'meta' => ['name' => 'case_a']],
        ['input' => 'b', 'meta' => ['name' => 'case_b']],
        ['input' => 'c', 'meta' => ['name' => 'case_c']],
    ], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([(string) $payload]);

    $generator = new DatasetGenerator('claude-sonnet-4-6');

    $cases = $generator->generate(
        criteria: 'single-letter inputs',
        schema: ['type' => 'string'],
        count: 3,
    );

    expect($cases)->toHaveCount(3)
        ->and($cases[0]['input'])->toBe('a')
        ->and($cases[2]['meta']['name'])->toBe('case_c');
});

it('validates each case has an input field', function (): void {
    $payload = json_encode([
        ['notinput' => 'a'],
    ], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([(string) $payload, (string) $payload]);

    $generator = new DatasetGenerator('claude-sonnet-4-6');

    $generator->generate('crit', ['type' => 'string'], 1);
})->throws(DatasetGeneratorException::class);

it('accepts cases with expected and meta fields', function (): void {
    $payload = json_encode([
        [
            'input' => 'hello',
            'expected' => 'world',
            'meta' => ['name' => 'hw'],
        ],
    ], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([(string) $payload]);

    $generator = new DatasetGenerator('claude-sonnet-4-6');

    $cases = $generator->generate('crit', ['type' => 'string'], 1);

    expect($cases[0]['input'])->toBe('hello')
        ->and($cases[0]['expected'])->toBe('world')
        ->and($cases[0]['meta'])->toBe(['name' => 'hw']);
});

it('retries on malformed JSON and succeeds', function (): void {
    $payload = json_encode([['input' => 'x']], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([
        'not json',
        (string) $payload,
    ]);

    $generator = new DatasetGenerator('claude-sonnet-4-6', maxRetries: 1);

    $cases = $generator->generate('crit', ['type' => 'string'], 1);

    expect($cases)->toHaveCount(1)
        ->and($cases[0]['input'])->toBe('x');
});

it('throws DatasetGeneratorException when all retries fail', function (): void {
    DatasetGeneratorAgent::fake([
        'junk one',
        'junk two',
    ]);

    $generator = new DatasetGenerator('claude-sonnet-4-6', maxRetries: 1);

    $generator->generate('crit', ['type' => 'string'], 1);
})->throws(DatasetGeneratorException::class);

it('rejects cases without input key', function (): void {
    $payload = json_encode([
        ['foo' => 'bar'],
    ], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([(string) $payload, (string) $payload]);

    $generator = new DatasetGenerator('claude-sonnet-4-6');

    $generator->generate('crit', ['type' => 'string'], 1);
})->throws(DatasetGeneratorException::class);

it('rejects non-array responses', function (): void {
    DatasetGeneratorAgent::fake([
        '{"not": "an array"}',
        '"still not an array"',
    ]);

    $generator = new DatasetGenerator('claude-sonnet-4-6');

    $generator->generate('crit', ['type' => 'string'], 1);
})->throws(DatasetGeneratorException::class);

it('includes the criteria in the prompt', function (): void {
    $captured = null;
    DatasetGeneratorAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return json_encode([['input' => 'x']], JSON_UNESCAPED_UNICODE) ?: '';
    });

    $generator = new DatasetGenerator('claude-sonnet-4-6');
    $generator->generate(
        criteria: 'we need cases about UNIQUE_CRITERIA_MARKER',
        schema: ['type' => 'string'],
        count: 1,
    );

    expect($captured)->toContain('UNIQUE_CRITERIA_MARKER');
});

it('includes the schema in the prompt', function (): void {
    $captured = null;
    DatasetGeneratorAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return json_encode([['input' => 'x']], JSON_UNESCAPED_UNICODE) ?: '';
    });

    $generator = new DatasetGenerator('claude-sonnet-4-6');
    $generator->generate(
        criteria: 'c',
        schema: ['type' => 'object', 'properties' => ['marker_field' => ['type' => 'string']]],
        count: 1,
    );

    expect($captured)->toContain('marker_field');
});

it('includes seed cases when provided', function (): void {
    $captured = null;
    DatasetGeneratorAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return json_encode([['input' => 'x']], JSON_UNESCAPED_UNICODE) ?: '';
    });

    $generator = new DatasetGenerator('claude-sonnet-4-6');
    $generator->generate(
        criteria: 'c',
        schema: ['type' => 'string'],
        count: 1,
        seedCases: [
            ['input' => 'SEED_MARKER_ONE'],
            ['input' => 'SEED_MARKER_TWO'],
        ],
    );

    expect($captured)->toContain('SEED_MARKER_ONE')
        ->and($captured)->toContain('SEED_MARKER_TWO')
        ->and($captured)->toContain('EXAMPLE CASES');
});

it('omits seed section when seedCases is null', function (): void {
    $captured = null;
    DatasetGeneratorAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return json_encode([['input' => 'x']], JSON_UNESCAPED_UNICODE) ?: '';
    });

    $generator = new DatasetGenerator('claude-sonnet-4-6');
    $generator->generate('c', ['type' => 'string'], 1);

    expect($captured)->not->toContain('EXAMPLE CASES');
});

it('uses the override model when specified', function (): void {
    $captured = null;
    DatasetGeneratorAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $model;

        return json_encode([['input' => 'x']], JSON_UNESCAPED_UNICODE) ?: '';
    });

    $generator = new DatasetGenerator('default-gen-model');
    $generator->generate('c', ['type' => 'string'], 1, model: 'override-model');

    expect($captured)->toBe('override-model');
});

it('uses the default model when none specified', function (): void {
    $captured = null;
    DatasetGeneratorAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $model;

        return json_encode([['input' => 'x']], JSON_UNESCAPED_UNICODE) ?: '';
    });

    $generator = new DatasetGenerator('default-gen-model');
    $generator->generate('c', ['type' => 'string'], 1);

    expect($captured)->toBe('default-gen-model');
});

it('extracts cases array from wrapping object', function (): void {
    $payload = json_encode([
        'cases' => [
            ['input' => 'wrapped-1'],
            ['input' => 'wrapped-2'],
        ],
    ], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([(string) $payload]);

    $generator = new DatasetGenerator('claude-sonnet-4-6');

    $cases = $generator->generate('c', ['type' => 'string'], 2);

    expect($cases)->toHaveCount(2)
        ->and($cases[0]['input'])->toBe('wrapped-1')
        ->and($cases[1]['input'])->toBe('wrapped-2');
});

it('generates a diverse set of cases (no duplicates in fake)', function (): void {
    $payload = json_encode([
        ['input' => 'alpha'],
        ['input' => 'bravo'],
        ['input' => 'charlie'],
        ['input' => 'delta'],
        ['input' => 'echo'],
    ], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([(string) $payload]);

    $generator = new DatasetGenerator('claude-sonnet-4-6');

    $cases = $generator->generate('c', ['type' => 'string'], 5);

    $inputs = array_map(static fn (array $case): mixed => $case['input'], $cases);
    expect(array_unique($inputs))->toHaveCount(5);
});

it('rejects empty default model', function (): void {
    new DatasetGenerator('');
})->throws(InvalidArgumentException::class);

it('rejects negative maxRetries', function (): void {
    new DatasetGenerator('m', maxRetries: -1);
})->throws(InvalidArgumentException::class);
