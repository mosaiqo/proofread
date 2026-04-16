<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Proofread;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    Proofread::registerPestExpectations();
});

it('passes when output matches an array schema', function (): void {
    $schema = [
        'type' => 'object',
        'required' => ['name'],
        'properties' => [
            'name' => ['type' => 'string'],
        ],
    ];

    expect(['name' => 'Boudy'])->toMatchSchema($schema);
});

it('passes when output matches a JSON string schema', function (): void {
    $schema = '{"type":"object","required":["name"],"properties":{"name":{"type":"string"}}}';

    expect('{"name":"Boudy"}')->toMatchSchema($schema);
});

it('passes when output matches a schema loaded from a file', function (): void {
    $path = __DIR__.'/../../fixtures/sample-schema.json';

    expect(['name' => 'Boudy'])->toMatchSchema($path);
});

it('fails when output violates the schema', function (): void {
    $schema = [
        'type' => 'object',
        'required' => ['name'],
        'properties' => [
            'name' => ['type' => 'string'],
        ],
    ];

    $caught = null;
    try {
        expect(['name' => 42])->toMatchSchema($schema);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('Schema violation');
});

it('includes the violation path in the failure message', function (): void {
    $schema = [
        'type' => 'object',
        'required' => ['user'],
        'properties' => [
            'user' => [
                'type' => 'object',
                'required' => ['email'],
                'properties' => [
                    'email' => ['type' => 'string'],
                ],
            ],
        ],
    ];

    $caught = null;
    try {
        expect(['user' => ['email' => 123]])->toMatchSchema($schema);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('/user/email');
});

it('handles malformed JSON output gracefully', function (): void {
    $schema = ['type' => 'object'];

    $caught = null;
    try {
        expect('{not valid json')->toMatchSchema($schema);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('not valid JSON');
});

it('supports negation', function (): void {
    $schema = [
        'type' => 'object',
        'required' => ['name'],
        'properties' => [
            'name' => ['type' => 'string'],
        ],
    ];

    expect(['name' => 42])->not->toMatchSchema($schema);
});

it('resolves file paths relative to cwd', function (): void {
    $path = 'tests/fixtures/sample-schema.json';

    expect(['name' => 'Boudy'])->toMatchSchema($path);
});
