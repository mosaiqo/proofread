<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mosaiqo\Proofread\Http\Livewire\CompareRuns;
use Mosaiqo\Proofread\Http\Livewire\DatasetsList;
use Mosaiqo\Proofread\Http\Livewire\RunDetail;
use Mosaiqo\Proofread\Http\Livewire\RunsList;
use Mosaiqo\Proofread\Http\Middleware\DashboardEnabled;

/** @var array<int, string> $middleware */
$middleware = (array) config('proofread.dashboard.middleware', ['web']);

/** @var string $path */
$path = (string) config('proofread.dashboard.path', 'evals');

Route::middleware(array_merge([DashboardEnabled::class], $middleware))
    ->prefix($path)
    ->name('proofread.')
    ->group(function () use ($path): void {
        Route::redirect('/', '/'.$path.'/runs');
        Route::get('/runs', RunsList::class)->name('runs.index');
        Route::get('/runs/{run}', RunDetail::class)->name('runs.show');
        Route::get('/compare', CompareRuns::class)->name('compare');
        Route::get('/datasets', DatasetsList::class)->name('datasets.index');
    });
