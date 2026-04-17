<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Cli\CliExecutionException;
use Mosaiqo\Proofread\Cli\CliTimeoutException;
use Mosaiqo\Proofread\Cli\Subjects\ClaudeCodeCliSubject;

function claudeStubPath(string $name): string
{
    return __DIR__.'/../../../Fixtures/Cli/'.$name;
}

it('parses successful claude CLI output', function (): void {
    $subject = ClaudeCodeCliSubject::make()->withBinary(claudeStubPath('claude-cli-stub.sh'));

    $invocation = $subject('hello');

    expect($invocation->output)->toBe('Hello from the fake Claude CLI stub');
});

it('extracts token counts from usage', function (): void {
    $subject = ClaudeCodeCliSubject::make()->withBinary(claudeStubPath('claude-cli-stub.sh'));

    $invocation = $subject('x');

    expect($invocation->metadata['tokens_in'])->toBe(50)
        ->and($invocation->metadata['tokens_out'])->toBe(100)
        ->and($invocation->metadata['tokens_total'])->toBe(150);
});

it('extracts total cost from output', function (): void {
    $subject = ClaudeCodeCliSubject::make()->withBinary(claudeStubPath('claude-cli-stub.sh'));

    $invocation = $subject('x');

    expect($invocation->metadata['cost_usd'])->toBe(0.0042);
});

it('extracts model from response', function (): void {
    $subject = ClaudeCodeCliSubject::make()->withBinary(claudeStubPath('claude-cli-stub.sh'));

    $invocation = $subject('x');

    expect($invocation->metadata['model'])->toBe('claude-sonnet-4-6');
});

it('extracts session_id and num_turns', function (): void {
    $subject = ClaudeCodeCliSubject::make()->withBinary(claudeStubPath('claude-cli-stub.sh'));

    $invocation = $subject('x');

    expect($invocation->metadata['session_id'])->toBe('test-session-abc')
        ->and($invocation->metadata['num_turns'])->toBe(1);
});

it('throws when CLI reports is_error', function (): void {
    $subject = ClaudeCodeCliSubject::make()->withBinary(claudeStubPath('claude-cli-error.sh'));

    expect(fn () => $subject('x'))
        ->toThrow(CliExecutionException::class, 'something went wrong');
});

it('throws on malformed JSON', function (): void {
    $subject = ClaudeCodeCliSubject::make()->withBinary(claudeStubPath('claude-cli-malformed.sh'));

    expect(fn () => $subject('x'))
        ->toThrow(CliExecutionException::class, 'Malformed JSON');
});

it('throws on empty output', function (): void {
    $subject = ClaudeCodeCliSubject::make()->withBinary(claudeStubPath('claude-cli-empty.sh'));

    expect(fn () => $subject('x'))
        ->toThrow(CliExecutionException::class, 'Empty output');
});

it('passes --model when configured', function (): void {
    $subject = ClaudeCodeCliSubject::make()
        ->withBinary(claudeStubPath('claude-cli-echo-args.sh'))
        ->withModel('claude-sonnet-4-6');

    $invocation = $subject('my prompt');

    // result field contains pipe-joined args from the echo stub
    expect($invocation->output)->toContain('--model|claude-sonnet-4-6');
});

it('passes --dangerously-skip-permissions when enabled', function (): void {
    $subject = ClaudeCodeCliSubject::make()
        ->withBinary(claudeStubPath('claude-cli-echo-args.sh'))
        ->skipPermissions();

    $invocation = $subject('hi');

    expect($invocation->output)->toContain('--dangerously-skip-permissions');
});

it('appends extra args', function (): void {
    $subject = ClaudeCodeCliSubject::make()
        ->withBinary(claudeStubPath('claude-cli-echo-args.sh'))
        ->withArgs(['--extra-flag', 'xyz']);

    $invocation = $subject('hi');

    expect($invocation->output)->toContain('--extra-flag|xyz');
});

it('is immutable via withX methods', function (): void {
    $base = ClaudeCodeCliSubject::make();
    $withModel = $base->withModel('claude-sonnet-4-6');

    expect($base)->not->toBe($withModel);

    $baseArgs = $base->args('x');
    $modelArgs = $withModel->args('x');

    expect($baseArgs)->not->toContain('claude-sonnet-4-6')
        ->and($modelArgs)->toContain('claude-sonnet-4-6');
});

it('reads custom binary path', function (): void {
    $path = claudeStubPath('claude-cli-stub.sh');
    $subject = ClaudeCodeCliSubject::make()->withBinary($path);

    expect($subject->binary())->toBe($path);
});

it('respects timeout', function (): void {
    $subject = ClaudeCodeCliSubject::make()
        ->withBinary(claudeStubPath('claude-cli-sleep.sh'))
        ->withTimeout(1);

    expect(fn () => $subject('x'))->toThrow(CliTimeoutException::class);
});
