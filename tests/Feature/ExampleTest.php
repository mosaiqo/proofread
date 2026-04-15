<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\ProofreadServiceProvider;

it('registers the service provider', function (): void {
    expect(app()->getLoadedProviders())
        ->toHaveKey(ProofreadServiceProvider::class);
});

it('loads the package config', function (): void {
    expect(config('proofread'))
        ->toBeArray()
        ->and(config('proofread.enabled'))->toBeTrue()
        ->and(config('proofread.judge.default_model'))->toBe('claude-haiku-4-5');
});

it('exposes a version string', function (): void {
    expect((new Proofread)->version())->toBeString();
});
