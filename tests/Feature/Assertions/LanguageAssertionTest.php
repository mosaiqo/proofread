<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\LanguageAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Judge\JudgeAgent;
use Mosaiqo\Proofread\Support\JudgeResult;

beforeEach(function (): void {
    config()->set('proofread.judge.default_model', 'default-judge');
    config()->set('proofread.judge.max_retries', 1);
    config()->set('ai.default', 'openai');
});

it('passes when the output is in the expected language', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 1.0, "reason": "Primarily English."}']);

    $assertion = LanguageAssertion::matches('en');

    $result = $assertion->run('The quick brown fox jumps over the lazy dog.');

    expect($result)->toBeInstanceOf(JudgeResult::class);
    expect($result->passed)->toBeTrue();
});

it('fails when the output is in a different language', function (): void {
    JudgeAgent::fake(['{"passed": false, "score": 0.0, "reason": "Text is in Spanish, not English."}']);

    $assertion = LanguageAssertion::matches('en');

    $result = $assertion->run('El zorro marrón rápido salta sobre el perro perezoso.');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('Spanish');
});

it('accepts ISO 639-1 codes and mentions them in the prompt', function (): void {
    $captured = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    LanguageAssertion::matches('fr')->run('Bonjour le monde.');

    expect($captured)->toContain('fr');
});

it('accepts common language names and mentions them in the prompt', function (): void {
    $captured = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    LanguageAssertion::matches('Spanish')->run('Hola mundo.');

    expect($captured)->toContain('spanish');
});

it('rejects empty language code', function (): void {
    LanguageAssertion::matches('');
})->throws(InvalidArgumentException::class);

it('uses the override model via using()', function (): void {
    $capturedModel = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$capturedModel): string {
        $capturedModel = $model;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    $assertion = LanguageAssertion::matches('en')->using('gpt-5');

    $result = $assertion->run('Hello world.');

    expect($capturedModel)->toBe('gpt-5');
    expect($result->judgeModel)->toBe('gpt-5');
});

it('fails when output is not a string', function (): void {
    $assertion = LanguageAssertion::matches('en');

    $result = $assertion->run(['not' => 'a string']);

    expect($result)->toBeInstanceOf(JudgeResult::class);
    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('string');
});

it('exposes name as "language"', function (): void {
    expect(LanguageAssertion::matches('en')->name())->toBe('language');
});

it('implements the Assertion contract', function (): void {
    expect(LanguageAssertion::matches('en'))->toBeInstanceOf(Assertion::class);
});

it('uses the default judge model', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 1.0, "reason": "ok"}']);

    $result = LanguageAssertion::matches('en')->run('Hello');

    expect($result->judgeModel)->toBe('default-judge');
});

it('fails gracefully when the judge throws', function (): void {
    JudgeAgent::fake(['junk', 'more junk']);

    $result = LanguageAssertion::matches('en')->run('Hello');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('Judge failed');
});
