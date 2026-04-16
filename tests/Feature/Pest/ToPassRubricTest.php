<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Judge\JudgeAgent;
use Mosaiqo\Proofread\Proofread;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    Proofread::registerPestExpectations();
    config()->set('proofread.judge.default_model', 'default-judge');
    config()->set('proofread.judge.max_retries', 1);
    config()->set('ai.default', 'openai');
});

it('passes when the rubric approves', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 1.0, "reason": "great"}']);

    expect('the output')->toPassRubric('must be concise');
});

it('fails with a clear message when the rubric rejects', function (): void {
    JudgeAgent::fake(['{"passed": false, "score": 0.2, "reason": "off-topic"}']);

    $caught = null;
    try {
        expect('the output')->toPassRubric('must stay on topic');
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf(ExpectationFailedException::class);
    expect($caught?->getMessage() ?? '')->toContain('must stay on topic');
});

it('includes the score and reason in the failure message', function (): void {
    JudgeAgent::fake(['{"passed": false, "score": 0.3, "reason": "too-short-answer"}']);

    $caught = null;
    try {
        expect('x')->toPassRubric('criterion');
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    $message = $caught?->getMessage() ?? '';
    expect($message)->toContain('0.3');
    expect($message)->toContain('too-short-answer');
});

it('includes the judge model in the failure message', function (): void {
    JudgeAgent::fake(['{"passed": false, "score": 0.1, "reason": "bad"}']);

    $caught = null;
    try {
        expect('x')->toPassRubric('criterion');
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    expect($caught?->getMessage() ?? '')->toContain('default-judge');
});

it('supports the model override option', function (): void {
    $captured = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $model;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    expect('x')->toPassRubric('criterion', ['model' => 'gpt-5']);

    expect($captured)->toBe('gpt-5');
});

it('supports the min_score override option', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 0.7, "reason": "ok"}']);

    expect('x')->toPassRubric('criterion', ['min_score' => 0.5]);
});

it('fails when min_score is not met even though judge passed', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 0.6, "reason": "barely"}']);

    $caught = null;
    try {
        expect('x')->toPassRubric('criterion', ['min_score' => 0.9]);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf(ExpectationFailedException::class);
});

it('passes the input option to the judge', function (): void {
    $capturedPrompt = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$capturedPrompt): string {
        $capturedPrompt = $prompt;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    expect('the-output')->toPassRubric('criterion', ['input' => 'the-specific-input']);

    expect($capturedPrompt)->toContain('the-specific-input');
    expect($capturedPrompt)->toContain('INPUT');
});

it('supports negation', function (): void {
    JudgeAgent::fake(['{"passed": false, "score": 0.1, "reason": "no"}']);

    expect('x')->not->toPassRubric('criterion');
});

it('includes retry count when retries happened', function (): void {
    JudgeAgent::fake([
        'not json',
        '{"passed": false, "score": 0.1, "reason": "bad"}',
    ]);

    $caught = null;
    try {
        expect('x')->toPassRubric('criterion');
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    expect($caught?->getMessage() ?? '')->toContain('retry');
});

it('truncates long criteria in the failure message', function (): void {
    JudgeAgent::fake(['{"passed": false, "score": 0.1, "reason": "bad"}']);

    $longCriteria = str_repeat('long-rule ', 20);

    $caught = null;
    try {
        expect('x')->toPassRubric($longCriteria);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    $message = $caught?->getMessage() ?? '';
    expect($message)->toContain('...');
});
