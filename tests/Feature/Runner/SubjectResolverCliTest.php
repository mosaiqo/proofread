<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Cli\CliInvocation;
use Mosaiqo\Proofread\Runner\SubjectInvocation;
use Mosaiqo\Proofread\Runner\SubjectResolver;
use Mosaiqo\Proofread\Tests\Fixtures\Cli\EchoCliSubject;
use Mosaiqo\Proofread\Tests\Fixtures\Cli\StdinCliSubject;

it('resolves a CliSubject into a SubjectInvocation closure', function (): void {
    $resolver = new SubjectResolver;

    $closure = $resolver->resolve(new EchoCliSubject('cli-ok'));

    $result = $closure('prompt', []);

    expect($result)->toBeInstanceOf(SubjectInvocation::class)
        ->and($result->output)->toBe('cli-ok');
});

it('populates metadata from CliInvocation into SubjectInvocation', function (): void {
    $resolver = new SubjectResolver;

    $closure = $resolver->resolve(new EchoCliSubject('x'));

    $result = $closure('prompt', []);

    expect($result->metadata)->toHaveKey('latency_ms')
        ->and($result->metadata['latency_ms'])->toBeFloat()
        ->and($result->metadata['tokens_in'])->toBe(10)
        ->and($result->metadata['tokens_out'])->toBe(20)
        ->and($result->metadata['provider'])->toBe('cli')
        ->and($result->metadata['raw'])->toBeInstanceOf(CliInvocation::class);
});

it('stringifies non-string inputs via json_encode before passing to CLI', function (): void {
    $resolver = new SubjectResolver;

    $closure = $resolver->resolve(new StdinCliSubject);

    $result = $closure(['k' => 'v', 'n' => 1], []);

    expect($result->output)->toBe('{"k":"v","n":1}');
});
