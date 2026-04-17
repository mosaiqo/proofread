<?php

declare(strict_types=1);

use Laravel\Ai\Contracts\HasStructuredOutput;
use Mosaiqo\Proofread\Assertions\StructuredOutputAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\StructuredClassifierAgent;

it('passes when output conforms to the agent schema', function (): void {
    $assertion = StructuredOutputAssertion::conformsTo(StructuredClassifierAgent::class);

    $result = $assertion->run(['sentiment' => 'positive', 'confidence' => 0.9]);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toContain('StructuredClassifierAgent');
});

it('fails when output is not valid JSON', function (): void {
    $assertion = StructuredOutputAssertion::conformsTo(StructuredClassifierAgent::class);

    $result = $assertion->run('not json at all');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('Output is not valid JSON');
});

it('fails with schema violation details', function (): void {
    $assertion = StructuredOutputAssertion::conformsTo(StructuredClassifierAgent::class);

    $result = $assertion->run('{"sentiment": "euphoric"}');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('Structured output violation');
});

it('accepts array output directly', function (): void {
    $assertion = StructuredOutputAssertion::conformsTo(StructuredClassifierAgent::class);

    $result = $assertion->run(['sentiment' => 'neutral']);

    expect($result->passed)->toBeTrue();
});

it('includes parsed data in metadata when passing', function (): void {
    $assertion = StructuredOutputAssertion::conformsTo(StructuredClassifierAgent::class);

    $result = $assertion->run('{"sentiment": "positive", "confidence": 0.75}');

    expect($result->passed)->toBeTrue();
    expect($result->metadata)->toHaveKey('parsed_data');
    expect($result->metadata['parsed_data'])->toMatchArray([
        'sentiment' => 'positive',
        'confidence' => 0.75,
    ]);
});

it('includes parsed data and violation path in metadata when failing', function (): void {
    $assertion = StructuredOutputAssertion::conformsTo(StructuredClassifierAgent::class);

    $result = $assertion->run(['sentiment' => 'happy']);

    expect($result->passed)->toBeFalse();
    expect($result->metadata)->toHaveKey('parsed_data');
    expect($result->metadata)->toHaveKey('violation_path');
    expect($result->metadata['parsed_data'])->toMatchArray(['sentiment' => 'happy']);
});

it('rejects classes that do not implement HasStructuredOutput', function (): void {
    StructuredOutputAssertion::conformsTo(EchoAgent::class);
})->throws(InvalidArgumentException::class, HasStructuredOutput::class);

it('rejects nonexistent classes', function (): void {
    StructuredOutputAssertion::conformsTo('Not\\A\\Real\\Class');
})->throws(InvalidArgumentException::class, 'does not exist');

it('exposes name as "structured_output"', function (): void {
    $assertion = StructuredOutputAssertion::conformsTo(StructuredClassifierAgent::class);

    expect($assertion->name())->toBe('structured_output');
});

it('implements the Assertion contract', function (): void {
    $assertion = StructuredOutputAssertion::conformsTo(StructuredClassifierAgent::class);

    expect($assertion)->toBeInstanceOf(Assertion::class);
});

it('returns AssertionResult instances', function (): void {
    $assertion = StructuredOutputAssertion::conformsTo(StructuredClassifierAgent::class);

    $pass = $assertion->run(['sentiment' => 'positive']);
    $fail = $assertion->run('nope');

    expect($pass)->toBeInstanceOf(AssertionResult::class);
    expect($fail)->toBeInstanceOf(AssertionResult::class);
});
