<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\JsonSchemaAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;

$schema = [
    'type' => 'object',
    'required' => ['name'],
    'properties' => [
        'name' => ['type' => 'string'],
    ],
];

it('passes when the JSON output matches the schema', function () use ($schema): void {
    $result = JsonSchemaAssertion::fromArray($schema)->run('{"name":"Boudy"}');

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Output conforms to schema');
});

it('passes when an array output matches the schema', function () use ($schema): void {
    $result = JsonSchemaAssertion::fromArray($schema)->run(['name' => 'Boudy']);

    expect($result->passed)->toBeTrue();
});

it('fails when a required property is missing', function () use ($schema): void {
    $result = JsonSchemaAssertion::fromArray($schema)->run(['foo' => 'bar']);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('name');
});

it('fails when a property has the wrong type', function () use ($schema): void {
    $result = JsonSchemaAssertion::fromArray($schema)->run(['name' => 42]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('/name');
    expect($result->reason)->toContain('string');
});

it('fails when the output is not valid JSON', function () use ($schema): void {
    $result = JsonSchemaAssertion::fromArray($schema)->run('not json');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toStartWith('Output is not valid JSON');
});

it('fails when the output is the wrong PHP type', function () use ($schema): void {
    $assertion = JsonSchemaAssertion::fromArray($schema);

    $intResult = $assertion->run(42);
    expect($intResult->passed)->toBeFalse();
    expect($intResult->reason)->toBe('Expected JSON-decodable string, array, or object output, got integer');

    $nullResult = $assertion->run(null);
    expect($nullResult->passed)->toBeFalse();
    expect($nullResult->reason)->toBe('Expected JSON-decodable string, array, or object output, got NULL');
});

it('builds from an array schema', function () use ($schema): void {
    $assertion = JsonSchemaAssertion::fromArray($schema);

    expect($assertion)->toBeInstanceOf(JsonSchemaAssertion::class);
});

it('builds from a JSON string schema', function (): void {
    $json = '{"type":"object","required":["name"],"properties":{"name":{"type":"string"}}}';
    $assertion = JsonSchemaAssertion::fromJson($json);

    $result = $assertion->run(['name' => 'ok']);
    expect($result->passed)->toBeTrue();
});

it('builds from a schema file', function (): void {
    $assertion = JsonSchemaAssertion::fromFile(__DIR__.'/../../Fixtures/sample-schema.json');

    $result = $assertion->run(['name' => 'ok']);
    expect($result->passed)->toBeTrue();
});

it('rejects invalid JSON when building from string', function (): void {
    JsonSchemaAssertion::fromJson('not json');
})->throws(InvalidArgumentException::class);

it('rejects missing files when building from file', function (): void {
    JsonSchemaAssertion::fromFile(__DIR__.'/../../fixtures/does-not-exist.json');
})->throws(InvalidArgumentException::class);

it('rejects invalid JSON in schema files', function (): void {
    $path = sys_get_temp_dir().'/proofread-invalid-schema-'.uniqid().'.json';
    file_put_contents($path, 'not json');

    $caught = null;
    try {
        JsonSchemaAssertion::fromFile($path);
    } catch (InvalidArgumentException $e) {
        $caught = $e;
    } finally {
        @unlink($path);
    }

    expect($caught)->toBeInstanceOf(InvalidArgumentException::class);
});

it('exposes its name as "json_schema"', function () use ($schema): void {
    expect(JsonSchemaAssertion::fromArray($schema)->name())->toBe('json_schema');
});

it('implements the Assertion contract', function () use ($schema): void {
    expect(JsonSchemaAssertion::fromArray($schema))->toBeInstanceOf(Assertion::class);
});
