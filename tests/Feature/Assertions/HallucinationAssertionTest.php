<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\HallucinationAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Judge\JudgeAgent;
use Mosaiqo\Proofread\Support\JudgeResult;

beforeEach(function (): void {
    config()->set('proofread.judge.default_model', 'default-judge');
    config()->set('proofread.judge.max_retries', 1);
    config()->set('ai.default', 'openai');
});

it('passes when the output contains no hallucinations', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 1.0, "reason": "Fully grounded in ground truth."}']);

    $assertion = HallucinationAssertion::against('The capital of France is Paris.');

    $result = $assertion->run('Paris is the capital of France.');

    expect($result)->toBeInstanceOf(JudgeResult::class);
    expect($result->passed)->toBeTrue();
    expect($result->score)->toBe(1.0);
});

it('fails when the output hallucinates facts', function (): void {
    JudgeAgent::fake(['{"passed": false, "score": 0.2, "reason": "Mentions population which is absent from ground truth."}']);

    $assertion = HallucinationAssertion::against('The capital of France is Paris.');

    $result = $assertion->run('Paris has 10 million inhabitants.');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('population');
});

it('includes the ground truth in the judge prompt', function (): void {
    $captured = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    HallucinationAssertion::against('Mars has two moons: Phobos and Deimos.')->run('Mars has two moons.');

    expect($captured)->toContain('Mars has two moons: Phobos and Deimos.');
    expect($captured)->toContain('GROUND TRUTH');
});

it('uses the default judge model', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 1.0, "reason": "ok"}']);

    $assertion = HallucinationAssertion::against('gt');
    $result = $assertion->run('x');

    expect($result->judgeModel)->toBe('default-judge');
});

it('uses the override model via using()', function (): void {
    $capturedModel = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$capturedModel): string {
        $capturedModel = $model;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    $assertion = HallucinationAssertion::against('gt')->using('gpt-5');
    $result = $assertion->run('x');

    expect($capturedModel)->toBe('gpt-5');
    expect($result->judgeModel)->toBe('gpt-5');
});

it('respects minScore threshold', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 0.7, "reason": "partially grounded"}']);

    $assertion = HallucinationAssertion::against('gt')->minScore(0.9);

    $result = $assertion->run('x');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('below');
});

it('rejects empty ground truth', function (): void {
    HallucinationAssertion::against('');
})->throws(InvalidArgumentException::class);

it('returns a JudgeResult', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 1.0, "reason": "ok"}']);

    $result = HallucinationAssertion::against('gt')->run('x');

    expect($result)->toBeInstanceOf(JudgeResult::class);
});

it('fails when output is not a string', function (): void {
    $assertion = HallucinationAssertion::against('gt');

    $result = $assertion->run(['not' => 'string']);

    expect($result)->toBeInstanceOf(JudgeResult::class);
    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('string');
});

it('exposes name as "hallucination"', function (): void {
    expect(HallucinationAssertion::against('gt')->name())->toBe('hallucination');
});

it('implements the Assertion contract', function (): void {
    expect(HallucinationAssertion::against('gt'))->toBeInstanceOf(Assertion::class);
});

it('fails gracefully when the judge throws', function (): void {
    JudgeAgent::fake(['junk', 'more junk']);

    $result = HallucinationAssertion::against('gt')->run('x');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('Judge failed');
});
