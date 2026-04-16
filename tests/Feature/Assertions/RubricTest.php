<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\Rubric;
use Mosaiqo\Proofread\Judge\JudgeAgent;
use Mosaiqo\Proofread\Support\JudgeResult;

beforeEach(function (): void {
    config()->set('proofread.judge.default_model', 'default-judge');
    config()->set('proofread.judge.max_retries', 1);
    config()->set('ai.default', 'openai');
});

it('passes when the judge approves with score above threshold', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 0.9, "reason": "looks right"}']);

    $assertion = Rubric::make('output must be concise')->minScore(0.5);

    $result = $assertion->run('the short answer');

    expect($result)->toBeInstanceOf(JudgeResult::class);
    expect($result->passed)->toBeTrue();
    expect($result->score)->toBe(0.9);
    expect($result->reason)->toBe('looks right');
});

it('fails when the judge disapproves', function (): void {
    JudgeAgent::fake(['{"passed": false, "score": 0.2, "reason": "off-topic"}']);

    $assertion = Rubric::make('output must mention the topic');

    $result = $assertion->run('unrelated');

    expect($result->passed)->toBeFalse();
    expect($result->score)->toBe(0.2);
    expect($result->reason)->toContain('off-topic');
});

it('fails when the judge approves but score is below minScore', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 0.6, "reason": "mostly ok"}']);

    $assertion = Rubric::make('criterion')->minScore(0.8);

    $result = $assertion->run('x');

    expect($result->passed)->toBeFalse();
    expect($result->score)->toBe(0.6);
    expect($result->reason)->toContain('below');
    expect($result->reason)->toContain('0.8');
});

it('passes with default minScore of 1.0 only when score is exactly 1.0', function (): void {
    JudgeAgent::fake([
        '{"passed": true, "score": 0.99, "reason": "almost"}',
        '{"passed": true, "score": 1.0, "reason": "exact"}',
    ]);

    $below = Rubric::make('criterion')->run('a');
    $exact = Rubric::make('criterion')->run('b');

    expect($below->passed)->toBeFalse();
    expect($exact->passed)->toBeTrue();
});

it('passes when minScore is lowered and score meets it', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 0.6, "reason": "good enough"}']);

    $assertion = Rubric::make('criterion')->minScore(0.5);

    $result = $assertion->run('x');

    expect($result->passed)->toBeTrue();
});

it('uses the default judge model', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 1.0, "reason": "ok"}']);

    $assertion = Rubric::make('criterion');
    $result = $assertion->run('x');

    expect($result->judgeModel)->toBe('default-judge');
});

it('uses the override model via using()', function (): void {
    $capturedModel = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$capturedModel): string {
        $capturedModel = $model;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    $assertion = Rubric::make('criterion')->using('gpt-5');
    $result = $assertion->run('x');

    expect($capturedModel)->toBe('gpt-5');
    expect($result->judgeModel)->toBe('gpt-5');
});

it('passes the case input to the judge prompt', function (): void {
    $captured = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    Rubric::make('criterion')->run('the-output', ['input' => 'the-input']);

    expect($captured)->toContain('the-input');
    expect($captured)->toContain('INPUT');
});

it('is immutable via using() and minScore()', function (): void {
    JudgeAgent::fake([
        '{"passed": true, "score": 0.9, "reason": "ok"}',
        '{"passed": true, "score": 0.9, "reason": "ok"}',
    ]);

    $base = Rubric::make('criterion');
    $withModel = $base->using('new-model');
    $withMin = $base->minScore(0.5);

    expect($withModel)->not->toBe($base);
    expect($withMin)->not->toBe($base);

    $r1 = $base->run('x');
    expect($r1->judgeModel)->toBe('default-judge');
});

it('rejects empty criteria', function (): void {
    Rubric::make('');
})->throws(InvalidArgumentException::class);

it('rejects empty model in using()', function (): void {
    Rubric::make('criterion')->using('');
})->throws(InvalidArgumentException::class);

it('rejects minScore outside 0-1', function (): void {
    Rubric::make('criterion')->minScore(1.5);
})->throws(InvalidArgumentException::class);

it('rejects negative minScore', function (): void {
    Rubric::make('criterion')->minScore(-0.1);
})->throws(InvalidArgumentException::class);

it('returns a JudgeResult', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 1.0, "reason": "ok"}']);

    $assertion = Rubric::make('criterion');
    $result = $assertion->run('x');

    expect($result)->toBeInstanceOf(JudgeResult::class);
});

it('exposes name as "rubric"', function (): void {
    expect(Rubric::make('criterion')->name())->toBe('rubric');
});

it('fails gracefully when the judge throws', function (): void {
    JudgeAgent::fake(['not json', 'still not json']);

    $assertion = Rubric::make('criterion');
    $result = $assertion->run('x');

    expect($result)->toBeInstanceOf(JudgeResult::class);
    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('Judge failed');
    expect($result->score)->toBeNull();
    expect($result->judgeModel)->toBe('default-judge');
});

it('includes judge metadata in the result when passing', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 1.0, "reason": "ok"}']);

    $result = Rubric::make('criterion')->run('x');

    expect($result->metadata)->toHaveKey('judge_model');
    expect($result->metadata)->toHaveKey('judge_raw_response');
});

it('is immutable when the same Rubric instance is reused', function (): void {
    JudgeAgent::fake([
        '{"passed": true, "score": 0.6, "reason": "first"}',
        '{"passed": true, "score": 0.6, "reason": "second"}',
    ]);

    $base = Rubric::make('criterion');
    $tight = $base->minScore(0.9);

    $loose = $base->run('x');
    $strict = $tight->run('y');

    expect($loose->passed)->toBeFalse();
    expect($strict->passed)->toBeFalse();
});

it('embeds retry count in fail metadata when judge exhausted retries', function (): void {
    JudgeAgent::fake(['junk', 'more junk']);

    $result = Rubric::make('criterion')->run('x');

    expect($result->passed)->toBeFalse();
    expect($result->retryCount)->toBeGreaterThan(0);
});
