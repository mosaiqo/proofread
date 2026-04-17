<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Lint\LintIssue;
use Mosaiqo\Proofread\Lint\LintReport;

it('reports no issues when the issues list is empty', function (): void {
    $report = new LintReport('App\\Agent', 'hello', []);

    expect($report->hasIssues())->toBeFalse();
    expect($report->hasErrors())->toBeFalse();
    expect($report->errorCount())->toBe(0);
    expect($report->warningCount())->toBe(0);
    expect($report->infoCount())->toBe(0);
});

it('counts issues by severity', function (): void {
    $report = new LintReport('App\\Agent', 'x', [
        LintIssue::error('a', 'e1'),
        LintIssue::error('b', 'e2'),
        LintIssue::warning('c', 'w1'),
        LintIssue::info('d', 'i1'),
    ]);

    expect($report->errorCount())->toBe(2);
    expect($report->warningCount())->toBe(1);
    expect($report->infoCount())->toBe(1);
    expect($report->hasErrors())->toBeTrue();
    expect($report->hasIssues())->toBeTrue();
});

it('filters issues by severity', function (): void {
    $report = new LintReport('App\\Agent', 'x', [
        LintIssue::error('a', 'e1'),
        LintIssue::warning('b', 'w1'),
        LintIssue::info('c', 'i1'),
    ]);

    expect($report->issuesWithSeverity('error'))->toHaveCount(1);
    expect($report->issuesWithSeverity('warning'))->toHaveCount(1);
    expect($report->issuesWithSeverity('info'))->toHaveCount(1);
});

it('reports hasErrors false when only warnings present', function (): void {
    $report = new LintReport('App\\Agent', 'x', [
        LintIssue::warning('a', 'w1'),
        LintIssue::info('b', 'i1'),
    ]);

    expect($report->hasErrors())->toBeFalse();
    expect($report->hasIssues())->toBeTrue();
});

it('exposes agent class and instructions verbatim', function (): void {
    $report = new LintReport('App\\Agent\\Foo', 'the instructions here', []);

    expect($report->agentClass)->toBe('App\\Agent\\Foo');
    expect($report->instructions)->toBe('the instructions here');
});
