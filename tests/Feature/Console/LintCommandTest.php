<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Judge\JudgeAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\AmbiguousAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\CleanAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\ContradictoryAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\NotAnAgent;

it('lints a single agent and exits 0 when clean', function (): void {
    $exit = Artisan::call('proofread:lint', [
        'agents' => [CleanAgent::class],
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0);
    expect($output)->toContain(CleanAgent::class);
    expect($output)->toContain('no issues');
});

it('lints multiple agents', function (): void {
    $exit = Artisan::call('proofread:lint', [
        'agents' => [CleanAgent::class, AmbiguousAgent::class],
    ]);

    $output = Artisan::output();

    expect($output)->toContain(CleanAgent::class);
    expect($output)->toContain(AmbiguousAgent::class);
    expect($exit)->toBe(0);
});

it('exits 1 when errors are found', function (): void {
    $exit = Artisan::call('proofread:lint', [
        'agents' => [ContradictoryAgent::class],
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(1);
    expect($output)->toContain('ERROR');
    expect($output)->toContain('contradiction');
});

it('exits 0 when only warnings or info are found', function (): void {
    $exit = Artisan::call('proofread:lint', [
        'agents' => [AmbiguousAgent::class],
    ]);

    expect($exit)->toBe(0);
});

it('exits 2 when class does not exist', function (): void {
    $exit = Artisan::call('proofread:lint', [
        'agents' => ['App\\Does\\Not\\Exist'],
    ]);

    expect($exit)->toBe(2);
});

it('exits 2 when class is not an Agent', function (): void {
    $exit = Artisan::call('proofread:lint', [
        'agents' => [NotAnAgent::class],
    ]);

    expect($exit)->toBe(2);
});

it('filters by severity=error', function (): void {
    $exit = Artisan::call('proofread:lint', [
        'agents' => [ContradictoryAgent::class],
        '--severity' => 'error',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(1);
    expect($output)->toContain('ERROR');
    expect($output)->not->toContain('INFO');
});

it('outputs JSON format', function (): void {
    $exit = Artisan::call('proofread:lint', [
        'agents' => [ContradictoryAgent::class],
        '--format' => 'json',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(1);

    $decoded = json_decode(trim($output), true);
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveKey('agents');
    expect($decoded)->toHaveKey('overall');
    expect($decoded['overall']['errors'])->toBeGreaterThan(0);
});

it('outputs Markdown format', function (): void {
    $exit = Artisan::call('proofread:lint', [
        'agents' => [ContradictoryAgent::class],
        '--format' => 'markdown',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(1);
    expect($output)->toContain('## Proofread prompt lint');
    expect($output)->toContain('contradiction');
});

it('includes the line number for ambiguity issues in table output', function (): void {
    Artisan::call('proofread:lint', [
        'agents' => [AmbiguousAgent::class],
    ]);

    $output = Artisan::output();

    expect($output)->toContain('line');
    expect($output)->toContain('ambiguity');
});

it('applies semantic quality rule with --with-judge', function (): void {
    config()->set('proofread.judge.default_model', 'default-judge');
    config()->set('proofread.judge.max_retries', 1);
    config()->set('ai.default', 'openai');

    JudgeAgent::fake([json_encode([
        'passed' => true,
        'score' => 0.8,
        'reason' => 'ok',
        'issues' => ['Be more specific about tone.'],
    ])]);

    $exit = Artisan::call('proofread:lint', [
        'agents' => [CleanAgent::class],
        '--with-judge' => true,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0);
    expect($output)->toContain('semantic_quality');
    expect($output)->toContain('Be more specific');
});

it('does not apply semantic rule without the flag', function (): void {
    config()->set('proofread.judge.default_model', 'default-judge');
    config()->set('proofread.judge.max_retries', 1);
    config()->set('ai.default', 'openai');

    JudgeAgent::fake([json_encode([
        'passed' => false,
        'score' => 0.1,
        'reason' => 'bad',
        'issues' => ['Would be reported if the flag were set.'],
    ])]);

    $exit = Artisan::call('proofread:lint', [
        'agents' => [CleanAgent::class],
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0);
    expect($output)->not->toContain('semantic_quality');
    expect($output)->not->toContain('Would be reported');
});
