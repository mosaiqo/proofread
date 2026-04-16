<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

it('redirects /evals to /evals/runs', function (): void {
    $response = $this->get('/evals');

    $response->assertRedirect('/evals/runs');
});

it('returns 200 on /evals/runs when gate passes', function (): void {
    $response = $this->get('/evals/runs');

    $response->assertOk();
});

it('returns 403 when viewEvals gate denies', function (): void {
    Gate::define('viewEvals', fn ($user = null) => false);

    $response = $this->get('/evals/runs');

    $response->assertForbidden();
});

it('respects the custom dashboard path config', function (): void {
    config()->set('proofread.dashboard.path', 'quality');

    // The route file reads the config at registration time; re-require it so
    // the quality prefix is mounted in the current test's router.
    require __DIR__.'/../../../routes/dashboard.php';

    $response = $this->get('/quality/runs');

    $response->assertOk();
});

it('serves the Livewire assets', function (): void {
    $response = $this->get('/evals/runs');

    $response->assertOk();
    $response->assertSee('livewire.min.js', false);
    $response->assertSee('alpinejs', false);
});

it('exposes the proofread:: views namespace', function (): void {
    expect(view()->exists('proofread::layout'))->toBeTrue();
});

it('registers the viewEvals gate', function (): void {
    expect(Gate::has('viewEvals'))->toBeTrue();
});

it('can disable the dashboard entirely', function (): void {
    config()->set('proofread.dashboard.enabled', false);

    $response = $this->get('/evals/runs');

    $response->assertNotFound();
});
