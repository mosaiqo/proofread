<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Shadow\MtRandRandomNumberProvider;

it('returns a float between 0 inclusive and 1 exclusive', function (): void {
    $provider = new MtRandRandomNumberProvider;

    for ($i = 0; $i < 1000; $i++) {
        $value = $provider->between01();

        expect($value)->toBeFloat()
            ->and($value)->toBeGreaterThanOrEqual(0.0)
            ->and($value)->toBeLessThanOrEqual(1.0);
    }
});

it('produces different values across calls', function (): void {
    $provider = new MtRandRandomNumberProvider;

    $values = [];
    for ($i = 0; $i < 1000; $i++) {
        $values[] = $provider->between01();
    }

    expect(count(array_unique($values)))->toBeGreaterThan(1);
});
