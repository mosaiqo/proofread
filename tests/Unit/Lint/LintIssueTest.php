<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Lint\LintIssue;

it('creates issues with valid severities', function (): void {
    $error = LintIssue::error('rule_a', 'boom');
    $warning = LintIssue::warning('rule_b', 'careful');
    $info = LintIssue::info('rule_c', 'fyi');

    expect($error->severity)->toBe('error');
    expect($warning->severity)->toBe('warning');
    expect($info->severity)->toBe('info');
    expect($error->ruleName)->toBe('rule_a');
    expect($warning->message)->toBe('careful');
    expect($info->message)->toBe('fyi');
});

it('rejects invalid severities', function (): void {
    new LintIssue('rule_x', 'critical', 'message');
})->throws(InvalidArgumentException::class);

it('exposes the suggestion and line number when provided', function (): void {
    $issue = new LintIssue(
        ruleName: 'ambiguity',
        severity: 'info',
        message: 'maybe is ambiguous',
        suggestion: 'Replace with an explicit rule',
        lineNumber: 4,
    );

    expect($issue->suggestion)->toBe('Replace with an explicit rule');
    expect($issue->lineNumber)->toBe(4);
});
