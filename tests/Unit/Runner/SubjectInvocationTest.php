<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Runner\SubjectInvocation;

it('creates an invocation with output and metadata', function (): void {
    $invocation = SubjectInvocation::make('hello', ['tokens_in' => 10, 'tokens_out' => 5]);

    expect($invocation->output)->toBe('hello');
    expect($invocation->metadata)->toBe(['tokens_in' => 10, 'tokens_out' => 5]);
});

it('defaults metadata to empty array', function (): void {
    $invocation = SubjectInvocation::make('hello');

    expect($invocation->output)->toBe('hello');
    expect($invocation->metadata)->toBe([]);
});

it('is immutable', function (): void {
    $invocation = SubjectInvocation::make('hello', ['a' => 1]);
    $reflection = new ReflectionClass($invocation);

    expect($reflection->isReadOnly())->toBeTrue();
});
