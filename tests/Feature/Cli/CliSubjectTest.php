<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Cli\CliExecutionException;
use Mosaiqo\Proofread\Cli\CliInvocation;
use Mosaiqo\Proofread\Cli\CliTimeoutException;
use Mosaiqo\Proofread\Tests\Fixtures\Cli\EchoCliSubject;
use Mosaiqo\Proofread\Tests\Fixtures\Cli\EnvCliSubject;
use Mosaiqo\Proofread\Tests\Fixtures\Cli\LongStderrCliSubject;
use Mosaiqo\Proofread\Tests\Fixtures\Cli\SleepCliSubject;
use Mosaiqo\Proofread\Tests\Fixtures\Cli\StderrCliSubject;
use Mosaiqo\Proofread\Tests\Fixtures\Cli\StdinCliSubject;
use Mosaiqo\Proofread\Tests\Fixtures\Cli\ThrowingParseCliSubject;

it('invokes the CLI and captures stdout', function (): void {
    $subject = new EchoCliSubject('hello world');

    $invocation = $subject('irrelevant prompt');

    expect($invocation)->toBeInstanceOf(CliInvocation::class)
        ->and($invocation->output)->toBe('hello world')
        ->and($invocation->stdout)->toBe('hello world');
});

it('captures stderr', function (): void {
    $subject = new StderrCliSubject('some stderr message');

    $invocation = $subject('x');

    expect($invocation->stderr)->toBe('some stderr message')
        ->and($invocation->stdout)->toBe('ok');
});

it('reports exit code', function (): void {
    $subject = new StderrCliSubject('warn', exitCode: 3);

    $invocation = $subject('x');

    expect($invocation->exitCode)->toBe(3)
        ->and($invocation->metadata['cli_exit_code'])->toBe(3);
});

it('measures duration', function (): void {
    $subject = new EchoCliSubject('x');

    $invocation = $subject('x');

    expect($invocation->durationMs)->toBeFloat()
        ->and($invocation->durationMs)->toBeGreaterThan(0.0);
});

it('populates metadata from parseOutput', function (): void {
    $subject = new EchoCliSubject('hi');

    $invocation = $subject('x');

    expect($invocation->metadata['tokens_in'])->toBe(10)
        ->and($invocation->metadata['tokens_out'])->toBe(20);
});

it('adds built-in metadata fields', function (): void {
    $subject = new EchoCliSubject('hi');

    $invocation = $subject('x');

    expect($invocation->metadata)->toHaveKey('cli_binary')
        ->and($invocation->metadata['cli_binary'])->toBe('/bin/sh')
        ->and($invocation->metadata)->toHaveKey('cli_exit_code')
        ->and($invocation->metadata)->toHaveKey('cli_stderr');
});

it('truncates stderr in metadata to 500 chars', function (): void {
    $subject = new LongStderrCliSubject;

    $invocation = $subject('x');

    expect(mb_strlen($invocation->metadata['cli_stderr']))->toBe(500)
        ->and(mb_strlen($invocation->stderr))->toBe(1000);
});

it('honors timeout and throws CliTimeoutException', function (): void {
    $subject = new SleepCliSubject(sleepSeconds: 5, timeoutSeconds: 1);

    expect(fn () => $subject('x'))->toThrow(CliTimeoutException::class);
});

it('wraps parseOutput errors in CliExecutionException', function (): void {
    $subject = new ThrowingParseCliSubject;

    expect(fn () => $subject('x'))->toThrow(CliExecutionException::class, 'parse boom');
});

it('uses stdin when usesStdin returns true', function (): void {
    $subject = new StdinCliSubject;

    $invocation = $subject('piped via stdin');

    expect($invocation->output)->toBe('piped via stdin');
});

it('estimates tokens from word count', function (): void {
    $subject = new EchoCliSubject;

    $estimate = $subject->estimateTokens('one two three four five six');

    expect($estimate)->toBeGreaterThan(0);
});

it('passes env vars to subprocess', function (): void {
    $subject = new EnvCliSubject(env: ['PROOFREAD_TEST_VAR' => 'secret-value']);

    $invocation = $subject('x');

    expect($invocation->output)->toBe('secret-value');
});
