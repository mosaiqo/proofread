<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Shadow\PiiSanitizer;

function defaultSanitizer(): PiiSanitizer
{
    return new PiiSanitizer(
        piiKeys: ['email', 'phone', 'ssn', 'password', 'api_key', 'token'],
        redactPatterns: [
            '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/' => '[EMAIL]',
        ],
        maxInputLength: 2000,
        maxOutputLength: 5000,
        redactedPlaceholder: '[REDACTED]',
    );
}

it('returns strings unchanged when no patterns or truncation apply', function (): void {
    $sanitizer = new PiiSanitizer(
        piiKeys: [],
        redactPatterns: [],
        maxInputLength: 2000,
        maxOutputLength: 5000,
    );

    expect($sanitizer->sanitizeInput('hello world'))->toBe('hello world');
});

it('redacts values at PII-matching keys case insensitively', function (): void {
    $sanitizer = defaultSanitizer();

    $input = ['email' => 'a@b.com', 'Email' => 'c@d.com', 'EMAIL' => 'e@f.com', 'name' => 'Alice'];
    $result = $sanitizer->sanitizeInput($input);

    expect($result)->toBe([
        'email' => '[REDACTED]',
        'Email' => '[REDACTED]',
        'EMAIL' => '[REDACTED]',
        'name' => 'Alice',
    ]);
});

it('recurses into nested arrays', function (): void {
    $sanitizer = defaultSanitizer();

    $input = ['user' => ['email' => 'x@y.com', 'id' => 1, 'profile' => ['phone' => '123']]];
    $result = $sanitizer->sanitizeInput($input);

    expect($result)->toBe([
        'user' => [
            'email' => '[REDACTED]',
            'id' => 1,
            'profile' => ['phone' => '[REDACTED]'],
        ],
    ]);
});

it('recurses into indexed arrays', function (): void {
    $sanitizer = defaultSanitizer();

    $input = ['messages' => [['email' => 'x@y.com'], ['body' => 'y']]];
    $result = $sanitizer->sanitizeInput($input);

    expect($result)->toBe([
        'messages' => [
            ['email' => '[REDACTED]'],
            ['body' => 'y'],
        ],
    ]);
});

it('applies redact patterns to strings', function (): void {
    $sanitizer = defaultSanitizer();

    $result = $sanitizer->sanitizeInput('Contact me at foo@bar.com please.');

    expect($result)->toBe('Contact me at [EMAIL] please.');
});

it('applies multiple patterns in order', function (): void {
    $sanitizer = new PiiSanitizer(
        piiKeys: [],
        redactPatterns: [
            '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/' => '[EMAIL]',
            '/\bfoo\b/' => '[FOO]',
        ],
    );

    $result = $sanitizer->sanitizeInput('foo a@b.com foo');

    expect($result)->toBe('[FOO] [EMAIL] [FOO]');
});

it('truncates long string inputs', function (): void {
    $sanitizer = new PiiSanitizer(maxInputLength: 10);

    $result = $sanitizer->sanitizeInput('abcdefghijklmnopqrstuvwxyz');

    expect($result)->toBeString()
        ->and($result)->toStartWith('abcdefghij')
        ->and($result)->toContain('[truncated')
        ->and($result)->toContain('16 chars omitted');
});

it('does not truncate strings under the limit', function (): void {
    $sanitizer = new PiiSanitizer(maxInputLength: 100);

    expect($sanitizer->sanitizeInput('short'))->toBe('short');
});

it('converts objects to arrays during sanitization', function (): void {
    $sanitizer = defaultSanitizer();

    $obj = new stdClass;
    $obj->email = 'a@b.com';
    $obj->name = 'Alice';

    $result = $sanitizer->sanitizeInput($obj);

    expect($result)->toBeArray()
        ->and($result)->toBe(['email' => '[REDACTED]', 'name' => 'Alice']);
});

it('replaces resources with the placeholder', function (): void {
    $sanitizer = defaultSanitizer();

    $handle = tmpfile();

    $result = $sanitizer->sanitizeInput($handle);

    expect($result)->toBe('[REDACTED]');

    if (is_resource($handle)) {
        fclose($handle);
    }
});

it('replaces closures with the placeholder', function (): void {
    $sanitizer = defaultSanitizer();

    $closure = fn (): string => 'secret';

    expect($sanitizer->sanitizeInput($closure))->toBe('[REDACTED]');
});

it('preserves scalar non-string values', function (): void {
    $sanitizer = defaultSanitizer();

    expect($sanitizer->sanitizeInput(42))->toBe(42)
        ->and($sanitizer->sanitizeInput(3.14))->toBe(3.14)
        ->and($sanitizer->sanitizeInput(true))->toBeTrue()
        ->and($sanitizer->sanitizeInput(false))->toBeFalse()
        ->and($sanitizer->sanitizeInput(null))->toBeNull();
});

it('sanitizes output with patterns and truncation', function (): void {
    $sanitizer = new PiiSanitizer(
        redactPatterns: [
            '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/' => '[EMAIL]',
        ],
        maxOutputLength: 20,
    );

    $result = $sanitizer->sanitizeOutput('Email a@b.com for more info and extra text here');

    expect($result)->toContain('[EMAIL]')
        ->and($result)->toContain('[truncated')
        ->and(strlen($result))->toBeGreaterThan(20);
});

it('builds from config with defaults for missing keys', function (): void {
    $sanitizer = PiiSanitizer::fromConfig([]);

    expect($sanitizer->piiKeys)->toBe([])
        ->and($sanitizer->redactPatterns)->toBe([])
        ->and($sanitizer->maxInputLength)->toBe(2000)
        ->and($sanitizer->maxOutputLength)->toBe(5000)
        ->and($sanitizer->redactedPlaceholder)->toBe('[REDACTED]');
});

it('builds from config with provided values', function (): void {
    $sanitizer = PiiSanitizer::fromConfig([
        'pii_keys' => ['email'],
        'redact_patterns' => ['/foo/' => '[FOO]'],
        'max_input_length' => 100,
        'max_output_length' => 200,
        'redacted_placeholder' => '[X]',
    ]);

    expect($sanitizer->piiKeys)->toBe(['email'])
        ->and($sanitizer->redactPatterns)->toBe(['/foo/' => '[FOO]'])
        ->and($sanitizer->maxInputLength)->toBe(100)
        ->and($sanitizer->maxOutputLength)->toBe(200)
        ->and($sanitizer->redactedPlaceholder)->toBe('[X]');
});

it('rejects invalid pii_keys type', function (): void {
    PiiSanitizer::fromConfig(['pii_keys' => 'not an array']);
})->throws(InvalidArgumentException::class);

it('rejects invalid redact_patterns type', function (): void {
    PiiSanitizer::fromConfig(['redact_patterns' => 'nope']);
})->throws(InvalidArgumentException::class);

it('rejects invalid max_input_length type', function (): void {
    PiiSanitizer::fromConfig(['max_input_length' => 'nope']);
})->throws(InvalidArgumentException::class);

it('rejects invalid max_output_length type', function (): void {
    PiiSanitizer::fromConfig(['max_output_length' => []]);
})->throws(InvalidArgumentException::class);

it('rejects invalid redacted_placeholder type', function (): void {
    PiiSanitizer::fromConfig(['redacted_placeholder' => 123]);
})->throws(InvalidArgumentException::class);

it('uses a custom redacted placeholder', function (): void {
    $sanitizer = new PiiSanitizer(
        piiKeys: ['email'],
        redactedPlaceholder: '***',
    );

    $result = $sanitizer->sanitizeInput(['email' => 'a@b.com']);

    expect($result)->toBe(['email' => '***']);
});

it('handles empty input gracefully', function (): void {
    $sanitizer = defaultSanitizer();

    expect($sanitizer->sanitizeInput(''))->toBe('')
        ->and($sanitizer->sanitizeInput([]))->toBe([])
        ->and($sanitizer->sanitizeInput(null))->toBeNull();
});
