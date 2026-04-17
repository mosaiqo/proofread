<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Lint\PromptLinter;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\AmbiguousAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\CleanAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\ContradictoryAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\NotAnAgent;

it('runs all rules against a clean agent without issues', function (): void {
    /** @var PromptLinter $linter */
    $linter = app(PromptLinter::class);

    $report = $linter->lintClass(CleanAgent::class);

    expect($report->agentClass)->toBe(CleanAgent::class);
    expect($report->hasIssues())->toBeFalse();
});

it('aggregates issues across multiple rules', function (): void {
    /** @var PromptLinter $linter */
    $linter = app(PromptLinter::class);

    $report = $linter->lintClass(AmbiguousAgent::class);

    expect($report->hasIssues())->toBeTrue();
    $ruleNames = array_map(fn ($i) => $i->ruleName, $report->issues);
    expect($ruleNames)->toContain('ambiguity');
});

it('detects error-level contradictions', function (): void {
    /** @var PromptLinter $linter */
    $linter = app(PromptLinter::class);

    $report = $linter->lintClass(ContradictoryAgent::class);

    expect($report->hasErrors())->toBeTrue();
    expect($report->issuesWithSeverity('error'))->not->toBe([]);
});

it('rejects non-existent classes', function (): void {
    /** @var PromptLinter $linter */
    $linter = app(PromptLinter::class);

    $linter->lintClass('App\\Does\\Not\\Exist');
})->throws(InvalidArgumentException::class, 'does not exist');

it('rejects classes that do not implement Agent', function (): void {
    /** @var PromptLinter $linter */
    $linter = app(PromptLinter::class);

    $linter->lintClass(NotAnAgent::class);
})->throws(InvalidArgumentException::class, 'does not implement');
