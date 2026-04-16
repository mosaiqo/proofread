<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use Mosaiqo\Proofread\Http\Livewire\ShadowPanel;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Models\ShadowEval;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function panelCapture(array $overrides = []): ShadowCapture
{
    $defaults = [
        'agent_class' => 'App\\Agents\\SupportAgent',
        'prompt_hash' => str_repeat('a', 64),
        'input_payload' => ['prompt' => 'hello there'],
        'output' => 'polite answer',
        'tokens_in' => 120,
        'tokens_out' => 45,
        'cost_usd' => 0.00123,
        'latency_ms' => 320.5,
        'model_used' => 'claude-haiku-4-5',
        'captured_at' => Carbon::now()->subHour(),
        'sample_rate' => 0.1,
        'is_anonymized' => true,
    ];

    $capture = new ShadowCapture;
    $capture->fill(array_merge($defaults, $overrides));
    $capture->save();

    return $capture;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function panelEval(ShadowCapture $capture, array $overrides = []): ShadowEval
{
    $defaults = [
        'capture_id' => $capture->id,
        'agent_class' => $capture->agent_class,
        'passed' => true,
        'total_assertions' => 1,
        'passed_assertions' => 1,
        'failed_assertions' => 0,
        'assertion_results' => [
            [
                'name' => 'contains',
                'passed' => true,
                'reason' => 'matched',
                'score' => null,
                'metadata' => [],
            ],
        ],
        'judge_cost_usd' => null,
        'judge_tokens_in' => null,
        'judge_tokens_out' => null,
        'evaluation_duration_ms' => 12.5,
        'evaluated_at' => Carbon::now(),
    ];

    $eval = new ShadowEval;
    $eval->fill(array_merge($defaults, $overrides));
    $eval->save();

    return $eval;
}

it('renders the shadow panel view', function (): void {
    Livewire::test(ShadowPanel::class)
        ->assertOk()
        ->assertSee('Shadow');
});

it('lists shadow captures', function (): void {
    $a = panelCapture(['agent_class' => 'App\\Agents\\Alpha']);
    $b = panelCapture(['agent_class' => 'App\\Agents\\Beta']);
    $c = panelCapture(['agent_class' => 'App\\Agents\\Gamma']);

    Livewire::test(ShadowPanel::class)
        ->assertSee('Alpha')
        ->assertSee('Beta')
        ->assertSee('Gamma');

    expect([$a->id, $b->id, $c->id])->each->not->toBeEmpty();
});

it('orders captures by captured_at desc', function (): void {
    $older = panelCapture([
        'agent_class' => 'App\\Agents\\Older',
        'captured_at' => Carbon::now()->subHours(5),
    ]);
    $newer = panelCapture([
        'agent_class' => 'App\\Agents\\Newer',
        'captured_at' => Carbon::now()->subMinutes(5),
    ]);

    Livewire::test(ShadowPanel::class)
        ->assertSeeInOrder(['Newer', 'Older']);

    expect([$older->id, $newer->id])->each->not->toBeEmpty();
});

it('shows capture stats', function (): void {
    $recent = panelCapture(['captured_at' => Carbon::now()->subHours(2)]);
    panelEval($recent, ['passed' => true, 'evaluated_at' => Carbon::now()->subHour()]);

    $old = panelCapture(['captured_at' => Carbon::now()->subDays(3)]);
    panelEval($old, ['passed' => false, 'evaluated_at' => Carbon::now()->subDays(3)]);

    panelCapture(['captured_at' => Carbon::now()->subHours(1)]); // pending

    Livewire::test(ShadowPanel::class)
        ->assertSet('stats.captures_24h', 2)
        ->assertSet('stats.pending', 1)
        ->assertSet('stats.seven_day_pass_rate', 0.5);
});

it('filters by agent class', function (): void {
    $keep = panelCapture(['agent_class' => 'App\\Agents\\Keep']);
    $drop = panelCapture(['agent_class' => 'App\\Agents\\Drop']);

    Livewire::test(ShadowPanel::class)
        ->set('agentFilter', 'App\\Agents\\Keep')
        ->assertSee('capture-'.$keep->id, false)
        ->assertDontSee('capture-'.$drop->id, false);
});

it('filters by pending status', function (): void {
    $pending = panelCapture(['agent_class' => 'App\\Agents\\Pending']);

    $evaluated = panelCapture(['agent_class' => 'App\\Agents\\Evaluated']);
    panelEval($evaluated);

    Livewire::test(ShadowPanel::class)
        ->set('statusFilter', 'pending')
        ->assertSee('capture-'.$pending->id, false)
        ->assertDontSee('capture-'.$evaluated->id, false);
});

it('filters by evaluated passed', function (): void {
    $passed = panelCapture(['agent_class' => 'App\\Agents\\Passed']);
    panelEval($passed, ['passed' => true]);

    $failed = panelCapture(['agent_class' => 'App\\Agents\\Failed']);
    panelEval($failed, ['passed' => false]);

    Livewire::test(ShadowPanel::class)
        ->set('statusFilter', 'evaluated_pass')
        ->assertSee('capture-'.$passed->id, false)
        ->assertDontSee('capture-'.$failed->id, false);
});

it('filters by evaluated failed', function (): void {
    $passed = panelCapture(['agent_class' => 'App\\Agents\\Passed']);
    panelEval($passed, ['passed' => true]);

    $failed = panelCapture(['agent_class' => 'App\\Agents\\Failed']);
    panelEval($failed, ['passed' => false]);

    Livewire::test(ShadowPanel::class)
        ->set('statusFilter', 'evaluated_fail')
        ->assertSee('capture-'.$failed->id, false)
        ->assertDontSee('capture-'.$passed->id, false);
});

it('searches by prompt hash substring', function (): void {
    $match = panelCapture([
        'agent_class' => 'App\\Agents\\HashMatch',
        'prompt_hash' => str_repeat('b', 32).str_repeat('c', 32),
    ]);
    $nonMatch = panelCapture([
        'agent_class' => 'App\\Agents\\HashNoMatch',
        'prompt_hash' => str_repeat('d', 64),
    ]);

    Livewire::test(ShadowPanel::class)
        ->set('search', 'bbbbbbbb')
        ->assertSee('capture-'.$match->id, false)
        ->assertDontSee('capture-'.$nonMatch->id, false);
});

it('paginates captures', function (): void {
    for ($i = 0; $i < 30; $i++) {
        panelCapture([
            'agent_class' => "App\\Agents\\Bulk$i",
            'captured_at' => Carbon::now()->subSeconds(30 - $i),
        ]);
    }

    $component = Livewire::test(ShadowPanel::class)->call('setPage', 2);

    expect($component->get('paginators')['page'] ?? null)->toBe(2);
});

it('opens the capture drawer on click', function (): void {
    $capture = panelCapture(['agent_class' => 'App\\Agents\\Drawer']);

    Livewire::test(ShadowPanel::class)
        ->call('selectCapture', $capture->id)
        ->assertSet('selectedCaptureId', $capture->id)
        ->assertSee('Drawer');
});

it('shows sanitized input in the drawer', function (): void {
    $capture = panelCapture([
        'agent_class' => 'App\\Agents\\Sanitized',
        'input_payload' => ['prompt' => 'safe text', 'email' => '[EMAIL]'],
        'is_anonymized' => true,
    ]);

    Livewire::test(ShadowPanel::class)
        ->call('selectCapture', $capture->id)
        ->assertSee('sanitized')
        ->assertSee('safe text');
});

it('shows the model tokens and cost in metadata section', function (): void {
    $capture = panelCapture([
        'agent_class' => 'App\\Agents\\Meta',
        'tokens_in' => 333,
        'tokens_out' => 222,
        'cost_usd' => 0.00456,
        'latency_ms' => 77.25,
        'model_used' => 'claude-opus-4-6',
        'sample_rate' => 0.25,
    ]);

    Livewire::test(ShadowPanel::class)
        ->call('selectCapture', $capture->id)
        ->assertSee('333')
        ->assertSee('222')
        ->assertSee('0.004560')
        ->assertSee('claude-opus-4-6')
        ->assertSee('77.3');
});

it('shows evaluation results when a ShadowEval exists', function (): void {
    $capture = panelCapture(['agent_class' => 'App\\Agents\\Evaluated']);
    panelEval($capture, [
        'passed' => true,
        'assertion_results' => [
            [
                'name' => 'contains-hello',
                'passed' => true,
                'reason' => 'matched needle',
                'score' => 0.95,
                'metadata' => [],
            ],
        ],
    ]);

    Livewire::test(ShadowPanel::class)
        ->call('selectCapture', $capture->id)
        ->assertSee('contains-hello')
        ->assertSee('matched needle');
});

it('indicates pending evaluation when no ShadowEval exists', function (): void {
    $capture = panelCapture(['agent_class' => 'App\\Agents\\Pending']);

    Livewire::test(ShadowPanel::class)
        ->call('selectCapture', $capture->id)
        ->assertSee('No evaluation yet');
});

it('generates a promote snippet with correct fields', function (): void {
    $capture = panelCapture([
        'agent_class' => 'App\\Agents\\Promote',
        'input_payload' => ['prompt' => 'the sanitized input body'],
        'captured_at' => Carbon::parse('2026-04-16 10:15:00', 'UTC'),
    ]);

    $component = Livewire::test(ShadowPanel::class)
        ->call('selectCapture', $capture->id);

    $component->assertSee($capture->id);
    $component->assertSee('the sanitized input body');
    $component->assertSee('capture_id');
    $component->assertSee('captured_at');
    $component->assertSee("'source' => 'shadow_capture'");
});

it('respects the viewEvals gate', function (): void {
    Gate::define('viewEvals', fn ($user = null) => false);

    $response = $this->get('/evals/shadow');

    $response->assertForbidden();
});

it('exposes filter state in URL query string', function (): void {
    $panel = new ShadowPanel;
    $reflection = new ReflectionClass($panel);

    foreach (['agentFilter', 'statusFilter', 'search'] as $property) {
        $attributes = $reflection->getProperty($property)->getAttributes(Url::class);
        expect($attributes)->not->toBeEmpty();
    }
});
