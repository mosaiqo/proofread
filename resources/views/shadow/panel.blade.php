<div>
    <h1 class="page-title">Shadow</h1>
    <p class="page-subtitle">Production captures and asynchronous evaluations.</p>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-card-title">Captures (24h)</span>
            <span class="stat-card-value">{{ $stats['captures_24h'] }}</span>
            <span class="stat-card-subtitle">Shadow captures in the last day</span>
        </div>

        <div class="stat-card">
            <span class="stat-card-title">Eval pass rate (7d)</span>
            @if ($stats['seven_day_pass_rate'] === null)
                <span class="stat-card-value muted">&mdash;</span>
                <span class="stat-card-subtitle">No evaluations yet</span>
            @else
                <span class="stat-card-value {{ $stats['seven_day_pass_rate'] >= 0.95 ? 'pass' : ($stats['seven_day_pass_rate'] < 0.75 ? 'fail' : '') }}">
                    {{ number_format($stats['seven_day_pass_rate'] * 100, 1) }}%
                </span>
                <span class="stat-card-subtitle">Shadow evaluations last 7 days</span>
            @endif
        </div>

        <div class="stat-card">
            <span class="stat-card-title">Pending evaluation</span>
            <span class="stat-card-value">{{ $stats['pending'] }}</span>
            <span class="stat-card-subtitle">Captures without a ShadowEval</span>
        </div>
    </div>

    <div class="filters">
        <div class="filter-field">
            <label for="shadow-agent-filter">Agent</label>
            <select id="shadow-agent-filter" wire:model.live="agentFilter">
                <option value="">All agents</option>
                @foreach ($agentOptions as $agent)
                    <option value="{{ $agent }}">{{ $agent }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-field">
            <label for="shadow-status-filter">Status</label>
            <select id="shadow-status-filter" wire:model.live="statusFilter">
                <option value="">All</option>
                <option value="pending">Pending</option>
                <option value="evaluated_pass">Evaluated passed</option>
                <option value="evaluated_fail">Evaluated failed</option>
            </select>
        </div>

        <div class="filter-field grow">
            <label for="shadow-search">Search</label>
            <input
                id="shadow-search"
                type="search"
                placeholder="Prompt hash or agent class..."
                wire:model.live.debounce.400ms="search"
            >
        </div>
    </div>

    @if ($captures->total() === 0)
        <div class="table-wrapper">
            <div class="empty-state">
                <h3>No captures yet</h3>
                <p>Enable shadow mode with <code>PROOFREAD_SHADOW_ENABLED=true</code> to start recording production agent calls.</p>
            </div>
        </div>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Captured</th>
                        <th>Tokens</th>
                        <th>Cost</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($captures as $capture)
                        @php
                            $latestEval = $capture->evals->first();
                            $shortAgent = class_basename($capture->agent_class);
                        @endphp
                        <tr
                            class="shadow-capture-row"
                            wire:key="capture-{{ $capture->id }}"
                            wire:click="selectCapture('{{ $capture->id }}')"
                        >
                            <td>
                                <span class="muted" title="{{ $capture->agent_class }}">{{ $shortAgent }}</span>
                            </td>
                            <td>
                                <span class="muted">{{ $capture->captured_at->diffForHumans() }}</span>
                            </td>
                            <td>
                                @if ($capture->tokens_in !== null || $capture->tokens_out !== null)
                                    {{ (int) $capture->tokens_in }} in / {{ (int) $capture->tokens_out }} out
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </td>
                            <td>
                                @if ($capture->cost_usd !== null)
                                    ${{ number_format($capture->cost_usd, 6) }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </td>
                            <td>
                                @if ($latestEval === null)
                                    <span class="badge shadow-status-pending">Pending</span>
                                @elseif ($latestEval->passed)
                                    <span class="badge badge-pass shadow-status-passed">Passed</span>
                                @else
                                    <span class="badge badge-fail shadow-status-failed">Failed</span>
                                @endif
                            </td>
                            <td class="action-cell">
                                <span class="muted">View</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($captures->hasPages())
                <div class="pagination-wrap">
                    {{ $captures->links() }}
                </div>
            @endif
        </div>
    @endif

    @if ($selectedCapture !== null)
        @php
            $selectedEval = $selectedCapture->evals->first();
            $outputString = (string) ($selectedCapture->output ?? '');
            $outputIsLong = strlen($outputString) > 500;
            $outputPreview = $outputIsLong ? substr($outputString, 0, 500) : $outputString;
            $inputJson = json_encode(
                $selectedCapture->input_payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
            $capturedAtIso = $selectedCapture->captured_at->toIso8601String();
            $sanitizedInputForSnippet = is_array($selectedCapture->input_payload) && isset($selectedCapture->input_payload['prompt'])
                ? (string) $selectedCapture->input_payload['prompt']
                : $inputJson;
            $promoteSnippet = "// Add this case to your dataset:\n".
                "[\n".
                "    'input' => ".var_export($sanitizedInputForSnippet, true).",\n".
                "    'expected' => null, // fill in manually\n".
                "    'meta' => [\n".
                "        'name' => 'promoted-from-shadow',\n".
                "        'source' => 'shadow_capture',\n".
                "        'capture_id' => '".$selectedCapture->id."',\n".
                "        'captured_at' => '".$capturedAtIso."',\n".
                "    ],\n".
                "],\n";
        @endphp

        <div
            x-data
            class="drawer-overlay"
            wire:click="closeCapture"
            x-transition.opacity
        ></div>
        <aside
            x-data="{}"
            class="drawer"
            role="dialog"
            aria-label="Capture details"
        >
            <div class="drawer-header">
                <div>
                    <div class="drawer-title">{{ class_basename($selectedCapture->agent_class) }}</div>
                    <div class="muted" title="{{ $selectedCapture->agent_class }}">{{ $selectedCapture->agent_class }}</div>
                    <div class="muted">
                        {{ $selectedCapture->captured_at->format('Y-m-d H:i:s') }}
                        <span class="dim">({{ $selectedCapture->captured_at->diffForHumans() }})</span>
                    </div>
                </div>
                <button type="button" class="drawer-close" wire:click="closeCapture" aria-label="Close">
                    &times;
                </button>
            </div>

            <div class="drawer-body">
                <section class="drawer-section">
                    <h3 class="drawer-section-title">Metadata</h3>
                    <dl class="meta-list">
                        <div>
                            <dt>Model</dt>
                            <dd>{{ $selectedCapture->model_used ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Latency</dt>
                            <dd>
                                @if ($selectedCapture->latency_ms !== null)
                                    {{ number_format($selectedCapture->latency_ms, 1) }} ms
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Tokens in</dt>
                            <dd>
                                @if ($selectedCapture->tokens_in !== null)
                                    {{ $selectedCapture->tokens_in }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Tokens out</dt>
                            <dd>
                                @if ($selectedCapture->tokens_out !== null)
                                    {{ $selectedCapture->tokens_out }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Cost</dt>
                            <dd>
                                @if ($selectedCapture->cost_usd !== null)
                                    ${{ number_format($selectedCapture->cost_usd, 6) }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Sample rate</dt>
                            <dd>{{ number_format($selectedCapture->sample_rate, 4) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="drawer-section">
                    <h3 class="drawer-section-title">
                        Input
                        @if ($selectedCapture->is_anonymized)
                            <span class="badge badge-warning">sanitized</span>
                        @endif
                    </h3>
                    <pre class="code-block">{{ $inputJson }}</pre>
                </section>

                <section class="drawer-section">
                    <h3 class="drawer-section-title">Output</h3>
                    @if ($outputString === '')
                        <span class="dim">&mdash;</span>
                    @elseif ($outputIsLong)
                        <div x-data="{ open: false }">
                            <pre class="code-block" x-show="!open" x-cloak>{{ $outputPreview }}&hellip;</pre>
                            <pre class="code-block" x-show="open" x-cloak>{{ $outputString }}</pre>
                            <button type="button" class="drawer-link" x-on:click="open = !open">
                                <span x-text="open ? 'Show less' : 'Show more'">Show more</span>
                            </button>
                        </div>
                    @else
                        <pre class="code-block">{{ $outputString }}</pre>
                    @endif
                </section>

                <section class="drawer-section">
                    <h3 class="drawer-section-title">Evaluation results</h3>
                    @if ($selectedEval === null)
                        <div class="muted">No evaluation yet. Run <code>php artisan shadow:evaluate</code> to evaluate pending captures.</div>
                    @else
                        <div>
                            @if ($selectedEval->passed)
                                <span class="badge badge-pass">Passed</span>
                            @else
                                <span class="badge badge-fail">Failed</span>
                            @endif
                            <span class="muted">
                                {{ $selectedEval->passed_assertions }} / {{ $selectedEval->total_assertions }} assertions
                            </span>
                        </div>

                        @php
                            $assertionResults = is_array($selectedEval->assertion_results)
                                ? $selectedEval->assertion_results
                                : [];
                        @endphp

                        @if (! empty($assertionResults))
                            <ul class="assertion-list">
                                @foreach ($assertionResults as $index => $assertion)
                                    @php
                                        $aName = is_array($assertion) && isset($assertion['name'])
                                            ? (string) $assertion['name']
                                            : 'assertion';
                                        $aPassed = is_array($assertion) && isset($assertion['passed'])
                                            ? (bool) $assertion['passed']
                                            : false;
                                        $aReason = is_array($assertion) && isset($assertion['reason'])
                                            ? (string) $assertion['reason']
                                            : '';
                                        $aScore = is_array($assertion) && isset($assertion['score'])
                                            ? $assertion['score']
                                            : null;
                                    @endphp
                                    <li class="assertion-row" wire:key="shadow-assertion-{{ $selectedEval->id }}-{{ $index }}">
                                        <div class="assertion-head">
                                            @if ($aPassed)
                                                <span class="badge badge-pass">PASS</span>
                                            @else
                                                <span class="badge badge-fail">FAIL</span>
                                            @endif
                                            <span class="assertion-name">{{ $aName }}</span>
                                            @if ($aScore !== null)
                                                <span class="muted">score: {{ is_numeric($aScore) ? number_format((float) $aScore, 3) : (string) $aScore }}</span>
                                            @endif
                                        </div>
                                        @if ($aReason !== '')
                                            <div class="assertion-reason muted">{{ $aReason }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </section>

                <section
                    class="drawer-section promote-panel"
                    x-data="{
                        open: false,
                        copied: false,
                        snippet: @js($promoteSnippet),
                        async copy() {
                            try {
                                await navigator.clipboard.writeText(this.snippet);
                                this.copied = true;
                                setTimeout(() => this.copied = false, 1500);
                            } catch (e) {}
                        }
                    }"
                >
                    <h3 class="drawer-section-title">Actions</h3>
                    <button type="button" class="drawer-link" x-on:click="open = !open">
                        <span x-text="open ? 'Hide promote panel' : 'Promote to dataset'">Promote to dataset</span>
                    </button>

                    <div x-show="open" x-cloak class="promote-panel-body">
                        <p class="muted">Paste this into your <code>Dataset::make()</code> cases array.</p>
                        <pre class="code-block promote-snippet">{{ $promoteSnippet }}</pre>
                        <button type="button" class="copy-button drawer-link" x-on:click="copy">
                            <span x-text="copied ? 'Copied!' : 'Copy snippet'">Copy snippet</span>
                        </button>
                    </div>
                </section>
            </div>
        </aside>
    @endif
</div>
