<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

it('registers the dashboard routes when Livewire is installed', function (): void {
    expect(Route::has('proofread.overview'))->toBeTrue();
})->skip(fn (): bool => ! class_exists(Livewire::class), 'Livewire is not installed.');

it('skips the dashboard routes when Livewire is not installed', function (): void {
    expect(Route::has('proofread.overview'))->toBeFalse();
})->skip(fn (): bool => class_exists(Livewire::class), 'Livewire is installed.');
