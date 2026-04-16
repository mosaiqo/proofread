<?php

declare(strict_types=1);

use Laravel\Ai\Contracts\HasStructuredOutput;
use Mosaiqo\Proofread\Assertions\JsonSchemaAssertion;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\StructuredClassifierAgent;

it('builds an assertion from an Agent class declaring a schema', function (): void {
    $assertion = JsonSchemaAssertion::fromAgent(StructuredClassifierAgent::class);

    expect($assertion)->toBeInstanceOf(JsonSchemaAssertion::class);
});

it('validates output that matches the agent-derived schema', function (): void {
    $assertion = JsonSchemaAssertion::fromAgent(StructuredClassifierAgent::class);

    $result = $assertion->run(['sentiment' => 'positive', 'confidence' => 0.9]);

    expect($result->passed)->toBeTrue();
});

it('rejects output that violates the agent-derived schema enum', function (): void {
    $assertion = JsonSchemaAssertion::fromAgent(StructuredClassifierAgent::class);

    $result = $assertion->run(['sentiment' => 'happy', 'confidence' => 0.9]);

    expect($result->passed)->toBeFalse();
});

it('requires sentiment as declared by the agent', function (): void {
    $assertion = JsonSchemaAssertion::fromAgent(StructuredClassifierAgent::class);

    $result = $assertion->run(['confidence' => 0.5]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('sentiment');
});

it('rejects a non-existent class', function (): void {
    JsonSchemaAssertion::fromAgent('Not\\A\\Real\\Class');
})->throws(InvalidArgumentException::class, 'does not exist');

it('rejects a class that does not implement HasStructuredOutput', function (): void {
    JsonSchemaAssertion::fromAgent(EchoAgent::class);
})->throws(InvalidArgumentException::class, HasStructuredOutput::class);
