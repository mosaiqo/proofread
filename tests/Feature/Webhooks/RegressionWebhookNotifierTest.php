<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mosaiqo\Proofread\Diff\EvalRunDiff;
use Mosaiqo\Proofread\Events\EvalRunRegressed;
use Mosaiqo\Proofread\Listeners\NotifyWebhookOnRegression;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun as EvalRunModel;
use Mosaiqo\Proofread\Runner\EvalPersister;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalResult as EvalResultVO;
use Mosaiqo\Proofread\Support\EvalRun as EvalRunVO;
use Mosaiqo\Proofread\Webhooks\RegressionWebhookNotifier;

/**
 * @param  list<array<string, mixed>>  $resultsData
 */
function seedWebhookRun(string $datasetName, array $resultsData): EvalRunModel
{
    $dataset = EvalDataset::query()->firstOrCreate(
        ['name' => $datasetName],
        ['case_count' => count($resultsData), 'checksum' => hash('sha256', $datasetName)],
    );

    $passCount = 0;
    $failCount = 0;
    foreach ($resultsData as $row) {
        if (($row['passed'] ?? true) === true) {
            $passCount++;
        } else {
            $failCount++;
        }
    }

    $run = new EvalRunModel;
    $run->fill([
        'dataset_id' => $dataset->id,
        'dataset_name' => $datasetName,
        'suite_class' => null,
        'subject_type' => 'unknown',
        'subject_class' => null,
        'commit_sha' => null,
        'model' => null,
        'passed' => $failCount === 0,
        'pass_count' => $passCount,
        'fail_count' => $failCount,
        'error_count' => 0,
        'total_count' => count($resultsData),
        'duration_ms' => 10.0,
        'total_cost_usd' => null,
        'total_tokens_in' => null,
        'total_tokens_out' => null,
    ]);
    $run->save();

    foreach ($resultsData as $row) {
        $result = new EvalResult;
        $result->fill([
            'run_id' => $run->id,
            'case_index' => $row['case_index'],
            'case_name' => $row['case_name'] ?? null,
            'input' => ['value' => 'x'],
            'output' => null,
            'expected' => null,
            'passed' => $row['passed'] ?? true,
            'assertion_results' => $row['assertion_results'] ?? [],
            'error_class' => null,
            'error_message' => null,
            'error_trace' => null,
            'duration_ms' => $row['duration_ms'] ?? 1.0,
            'latency_ms' => null,
            'tokens_in' => null,
            'tokens_out' => null,
            'cost_usd' => $row['cost_usd'] ?? null,
            'model' => null,
        ]);
        $result->save();
    }

    return $run->fresh(['results']) ?? $run;
}

function buildRegressionEvent(string $datasetName = 'ds-webhook'): EvalRunRegressed
{
    $base = seedWebhookRun($datasetName, [
        ['case_index' => 0, 'passed' => true, 'cost_usd' => 0.01],
        ['case_index' => 1, 'passed' => true, 'cost_usd' => 0.02],
    ]);
    $head = seedWebhookRun($datasetName, [
        ['case_index' => 0, 'passed' => false, 'cost_usd' => 0.03],
        ['case_index' => 1, 'passed' => true, 'cost_usd' => 0.04],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    return new EvalRunRegressed($base, $head, $delta);
}

it('posts to Slack webhook when configured', function (): void {
    Http::fake();

    $event = buildRegressionEvent();

    $notifier = new RegressionWebhookNotifier(
        app(HttpFactory::class),
        ['slack' => ['url' => 'https://hooks.slack.test/incoming', 'format' => 'slack']],
    );
    $notifier->notify($event);

    Http::assertSent(function (HttpRequest $request): bool {
        if ($request->url() !== 'https://hooks.slack.test/incoming') {
            return false;
        }

        $body = (string) $request->body();

        return str_contains($body, 'Eval regression detected');
    });
});

it('posts to Discord webhook when configured', function (): void {
    Http::fake();

    $event = buildRegressionEvent();

    $notifier = new RegressionWebhookNotifier(
        app(HttpFactory::class),
        ['discord' => ['url' => 'https://discord.test/webhook', 'format' => 'discord']],
    );
    $notifier->notify($event);

    Http::assertSent(function (HttpRequest $request): bool {
        if ($request->url() !== 'https://discord.test/webhook') {
            return false;
        }

        $body = (string) $request->body();

        return str_contains($body, 'Eval regression')
            && str_contains($body, 'embeds');
    });
});

it('posts to generic webhook when configured', function (): void {
    Http::fake();

    $event = buildRegressionEvent();

    $notifier = new RegressionWebhookNotifier(
        app(HttpFactory::class),
        ['generic' => ['url' => 'https://ops.test/hook', 'format' => 'generic']],
    );
    $notifier->notify($event);

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://ops.test/hook';
    });
});

it('posts to multiple webhooks', function (): void {
    Http::fake();

    $event = buildRegressionEvent();

    $notifier = new RegressionWebhookNotifier(
        app(HttpFactory::class),
        [
            'slack' => ['url' => 'https://hooks.slack.test/incoming', 'format' => 'slack'],
            'discord' => ['url' => 'https://discord.test/webhook', 'format' => 'discord'],
            'generic' => ['url' => 'https://ops.test/hook', 'format' => 'generic'],
        ],
    );
    $notifier->notify($event);

    Http::assertSentCount(3);
});

it('includes regression counts in the Slack payload', function (): void {
    Http::fake();

    $event = buildRegressionEvent();

    $notifier = new RegressionWebhookNotifier(
        app(HttpFactory::class),
        ['slack' => ['url' => 'https://hooks.slack.test/incoming', 'format' => 'slack']],
    );
    $notifier->notify($event);

    Http::assertSent(function (HttpRequest $request) use ($event): bool {
        $body = (string) $request->body();

        return str_contains($body, (string) $event->delta->regressions)
            && str_contains($body, 'Regressions')
            && str_contains($body, $event->delta->datasetName)
            && str_contains($body, $event->baseRun->id)
            && str_contains($body, $event->headRun->id);
    });
});

it('includes regression counts in the Discord payload', function (): void {
    Http::fake();

    $event = buildRegressionEvent();

    $notifier = new RegressionWebhookNotifier(
        app(HttpFactory::class),
        ['discord' => ['url' => 'https://discord.test/webhook', 'format' => 'discord']],
    );
    $notifier->notify($event);

    Http::assertSent(function (HttpRequest $request) use ($event): bool {
        $payload = $request->data();

        expect($payload)->toHaveKey('embeds');
        expect($payload['embeds'])->toBeArray()->toHaveCount(1);

        $embed = $payload['embeds'][0];
        $fieldNames = array_column($embed['fields'], 'name');

        return in_array('Regressions', $fieldNames, true)
            && in_array('Improvements', $fieldNames, true)
            && in_array('Cost delta', $fieldNames, true)
            && in_array('Duration delta', $fieldNames, true)
            && in_array('Base run', $fieldNames, true)
            && in_array('Head run', $fieldNames, true)
            && $embed['title'] === 'Eval regression: '.$event->delta->datasetName;
    });
});

it('includes the full delta in the generic payload', function (): void {
    Http::fake();

    $event = buildRegressionEvent();

    $notifier = new RegressionWebhookNotifier(
        app(HttpFactory::class),
        ['generic' => ['url' => 'https://ops.test/hook', 'format' => 'generic']],
    );
    $notifier->notify($event);

    Http::assertSent(function (HttpRequest $request) use ($event): bool {
        $payload = $request->data();

        return $payload['base_run_id'] === $event->baseRun->id
            && $payload['head_run_id'] === $event->headRun->id
            && $payload['dataset_name'] === $event->delta->datasetName
            && $payload['regressions'] === $event->delta->regressions
            && $payload['improvements'] === $event->delta->improvements
            && $payload['has_regressions'] === true
            && is_array($payload['cases']);
    });
});

it('throws on unknown format', function (): void {
    $event = buildRegressionEvent();

    $notifier = new RegressionWebhookNotifier(
        app(HttpFactory::class),
        ['bad' => ['url' => 'https://ops.test/hook', 'format' => 'nope']],
    );

    expect(fn () => $notifier->notify($event))->toThrow(InvalidArgumentException::class);
});

it('does nothing when webhooks are disabled', function (): void {
    Http::fake();

    config()->set('proofread.webhooks.enabled', false);
    config()->set('proofread.webhooks.regressions', [
        'slack' => ['url' => 'https://hooks.slack.test/incoming', 'format' => 'slack'],
    ]);

    $base = seedWebhookRun('ds-disabled', [['case_index' => 0, 'passed' => true]]);
    $base->created_at = now()->subMinute();
    $base->save();

    // Build a head run VO and persist via EvalPersister to trigger event chain.
    $headVo = EvalRunVO::make(
        Dataset::make('ds-disabled', [['input' => 'x']]),
        [
            EvalResultVO::make(
                ['input' => 'x'],
                'y',
                [AssertionResult::fail('nope', null, ['assertion_name' => 'contains'])],
                1.0,
            ),
        ],
        10.0,
    );

    (new EvalPersister)->persist($headVo);

    Http::assertNothingSent();
});

it('fires a webhook when EvalPersister persists a regressing run', function (): void {
    Http::fake();

    config()->set('proofread.webhooks.enabled', true);
    config()->set('proofread.webhooks.regressions', [
        'slack' => ['url' => 'https://hooks.slack.test/incoming', 'format' => 'slack'],
    ]);

    // Re-bind notifier with fresh config.
    app()->forgetInstance(RegressionWebhookNotifier::class);

    // Re-register listener with new config. Since the provider wires this in
    // boot() conditionally, in tests we re-listen manually to the event.
    Event::listen(EvalRunRegressed::class, NotifyWebhookOnRegression::class);

    $base = seedWebhookRun('ds-e2e', [['case_index' => 0, 'passed' => true]]);
    $base->created_at = now()->subMinute();
    $base->save();

    $headVo = EvalRunVO::make(
        Dataset::make('ds-e2e', [['input' => 'x']]),
        [
            EvalResultVO::make(
                ['input' => 'x'],
                'y',
                [AssertionResult::fail('nope', null, ['assertion_name' => 'contains'])],
                1.0,
            ),
        ],
        10.0,
    );

    (new EvalPersister)->persist($headVo);

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://hooks.slack.test/incoming'
            && str_contains((string) $request->body(), 'Eval regression detected');
    });
});

it('exposes a slack payload shape with blocks and fields', function (): void {
    Http::fake();

    $event = buildRegressionEvent();

    $notifier = new RegressionWebhookNotifier(
        app(HttpFactory::class),
        ['slack' => ['url' => 'https://hooks.slack.test/incoming', 'format' => 'slack']],
    );
    $notifier->notify($event);

    Http::assertSent(function (HttpRequest $request) use ($event): bool {
        $payload = $request->data();

        expect($payload)->toHaveKey('blocks');
        expect($payload['blocks'])->toBeArray();
        expect($payload['blocks'][0]['type'])->toBe('header');
        expect($payload['blocks'][0]['text']['text'])
            ->toBe('Eval regression detected: '.$event->delta->datasetName);

        $fields = $payload['blocks'][1]['fields'];
        $texts = array_column($fields, 'text');
        $joined = implode("\n", $texts);

        return str_contains($joined, '*Regressions:*')
            && str_contains($joined, '*Improvements:*')
            && str_contains($joined, '*Cost delta:*')
            && str_contains($joined, '*Duration delta:*');
    });
});

it('serializes an EvalRunDelta to the same shape as the MCP get_eval_run_diff tool', function (): void {
    Http::fake();

    $event = buildRegressionEvent();

    $notifier = new RegressionWebhookNotifier(
        app(HttpFactory::class),
        ['generic' => ['url' => 'https://ops.test/hook', 'format' => 'generic']],
    );
    $notifier->notify($event);

    Http::assertSent(function (HttpRequest $request) use ($event): bool {
        $payload = $request->data();

        // Validate that the serialized case shape matches the MCP shape.
        expect($payload['cases'])->toBeArray();
        $firstCase = $payload['cases'][0];

        return array_keys($firstCase) === [
            'case_index',
            'case_name',
            'status',
            'base_passed',
            'head_passed',
            'base_cost_usd',
            'head_cost_usd',
            'base_duration_ms',
            'head_duration_ms',
            'new_failures',
            'fixed_failures',
        ] && $payload['total_cases'] === $event->delta->totalCases
            && $payload['stable_passes'] === $event->delta->stablePasses
            && $payload['stable_failures'] === $event->delta->stableFailures;
    });
});
