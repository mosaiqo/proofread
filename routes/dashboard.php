<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mosaiqo\Proofread\Http\Controllers\ExportComparisonController;
use Mosaiqo\Proofread\Http\Controllers\ExportRunController;
use Mosaiqo\Proofread\Http\Livewire\CompareRuns;
use Mosaiqo\Proofread\Http\Livewire\ComparisonDetail;
use Mosaiqo\Proofread\Http\Livewire\ComparisonsList;
use Mosaiqo\Proofread\Http\Livewire\CostsBreakdown;
use Mosaiqo\Proofread\Http\Livewire\DatasetsList;
use Mosaiqo\Proofread\Http\Livewire\Overview;
use Mosaiqo\Proofread\Http\Livewire\RunDetail;
use Mosaiqo\Proofread\Http\Livewire\RunsList;
use Mosaiqo\Proofread\Http\Livewire\ShadowPanel;
use Mosaiqo\Proofread\Http\Middleware\DashboardEnabled;

/** @var array<int, string> $middleware */
$middleware = (array) config('proofread.dashboard.middleware', ['web']);

/** @var string $path */
$path = (string) config('proofread.dashboard.path', 'evals');

Route::middleware(array_merge([DashboardEnabled::class], $middleware))
    ->prefix($path)
    ->name('proofread.')
    ->group(function () use ($path): void {
        Route::redirect('/', '/'.$path.'/overview');
        Route::get('/overview', Overview::class)->name('overview');
        Route::get('/runs', RunsList::class)->name('runs.index');
        Route::get('/runs/{run}/export', ExportRunController::class)->name('runs.export');
        Route::get('/runs/{run}', RunDetail::class)->name('runs.show');
        Route::get('/compare', CompareRuns::class)->name('compare');
        Route::get('/comparisons', ComparisonsList::class)->name('comparisons.index');
        Route::get('/comparisons/{comparison}/export', ExportComparisonController::class)->name('comparisons.export');
        Route::get('/comparisons/{comparison}', ComparisonDetail::class)->name('comparisons.show');
        Route::get('/datasets', DatasetsList::class)->name('datasets.index');
        Route::get('/costs', CostsBreakdown::class)->name('costs');
        Route::get('/shadow', ShadowPanel::class)->name('shadow');
    });
