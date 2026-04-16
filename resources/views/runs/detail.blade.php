<div>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('proofread.runs.index') }}">Runs</a>
        <span class="breadcrumb-sep" aria-hidden="true">/</span>
        <span class="breadcrumb-current">{{ $run->dataset_name }}</span>
    </nav>

    <div class="run-header">
        <div class="run-header-main">
            <h1 class="page-title">{{ $run->dataset_name }}</h1>
            @if ($run->passed)
                <span class="badge badge-pass">Passed</span>
            @else
                <span class="badge badge-fail">Failed</span>
            @endif
        </div>
        <div class="run-header-meta muted">
            <span>{{ number_format($run->duration_ms, 1) }} ms</span>
            <span class="sep">&middot;</span>
            @if ($run->total_cost_usd !== null)
                <span>${{ number_format($run->total_cost_usd, 4) }}</span>
                <span class="sep">&middot;</span>
            @endif
            @if ($run->total_tokens_in !== null || $run->total_tokens_out !== null)
                <span>{{ (int) $run->total_tokens_in }} in / {{ (int) $run->total_tokens_out }} out tokens</span>
                <span class="sep">&middot;</span>
            @endif
            @if ($run->model)
                <span>{{ $run->model }}</span>
                <span class="sep">&middot;</span>
            @endif
            @if ($run->commit_sha)
                <span>{{ $run->commit_sha }}</span>
                <span class="sep">&middot;</span>
            @endif
            <span>{{ $run->created_at?->format('Y-m-d H:i:s') }}</span>
            @if ($run->created_at)
                <span class="dim">({{ $run->created_at->diffForHumans() }})</span>
            @endif
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-tile">
            <span class="summary-tile-label">Passed</span>
            <span class="summary-tile-value pass">{{ $summary['passed'] }}</span>
        </div>
        <div class="summary-tile">
            <span class="summary-tile-label">Failed</span>
            <span class="summary-tile-value fail">{{ $summary['failed'] }}</span>
        </div>
        <div class="summary-tile">
            <span class="summary-tile-label">Errors</span>
            <span class="summary-tile-value">{{ $summary['errors'] }}</span>
        </div>
        <div class="summary-tile">
            <span class="summary-tile-label">Pass rate</span>
            <span class="summary-tile-value">{{ number_format($summary['pass_rate'] * 100, 1) }}%</span>
        </div>
        <div class="summary-tile">
            <span class="summary-tile-label">Duration</span>
            <span class="summary-tile-value">{{ number_format($summary['duration_ms'], 1) }} ms</span>
        </div>
        <div class="summary-tile">
            <span class="summary-tile-label">Cost</span>
            <span class="summary-tile-value">
                @if ($summary['total_cost_usd'] !== null)
                    ${{ number_format($summary['total_cost_usd'], 4) }}
                @else
                    <span class="dim">&mdash;</span>
                @endif
            </span>
        </div>
    </div>

    <div class="cases-toolbar">
        <label class="inline-toggle">
            <input type="checkbox" wire:click="toggleFailures" @checked($onlyFailures)>
            <span>Only failures</span>
        </label>
    </div>

    @if ($cases->isEmpty())
        <div class="table-wrapper">
            <div class="empty-state">
                <h3>No cases to show</h3>
                <p>This run has no case results yet, or all cases are filtered out.</p>
            </div>
        </div>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th>Cost</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cases as $case)
                        <tr wire:key="case-{{ $case->id }}" wire:click="selectCase('{{ $case->id }}')">
                            <td>{{ $case->case_index }}</td>
                            <td>{{ $case->case_name ?? 'Case '.$case->case_index }}</td>
                            <td>
                                @if ($case->error_class)
                                    <span class="badge badge-warning">ERR</span>
                                @elseif ($case->passed)
                                    <span class="badge badge-pass">PASS</span>
                                @else
                                    <span class="badge badge-fail">FAIL</span>
                                @endif
                            </td>
                            <td>{{ number_format($case->duration_ms, 1) }} ms</td>
                            <td>
                                @if ($case->cost_usd !== null)
                                    ${{ number_format($case->cost_usd, 6) }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </td>
                            <td class="action-cell">
                                <span class="muted">View</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($selectedCase !== null)
        <div
            x-data
            class="drawer-overlay"
            wire:click="closeCase"
            x-transition.opacity
        ></div>
        <aside
            x-data="{}"
            class="drawer"
            role="dialog"
            aria-label="Case details"
            x-transition:enter="drawer-enter"
            x-transition:enter-start="drawer-enter-start"
            x-transition:enter-end="drawer-enter-end"
        >
            <div class="drawer-header">
                <div>
                    <div class="drawer-title">{{ $selectedCase->case_name ?? 'Case '.$selectedCase->case_index }}</div>
                    <div class="muted">Case #{{ $selectedCase->case_index }}</div>
                </div>
                <button type="button" class="drawer-close" wire:click="closeCase" aria-label="Close">
                    &times;
                </button>
            </div>

            <div class="drawer-body">
                <section class="drawer-section">
                    <h3 class="drawer-section-title">Status</h3>
                    <div>
                        @if ($selectedCase->error_class)
                            <span class="badge badge-warning">ERR</span>
                        @elseif ($selectedCase->passed)
                            <span class="badge badge-pass">PASS</span>
                        @else
                            <span class="badge badge-fail">FAIL</span>
                        @endif
                    </div>
                </section>

                <section class="drawer-section">
                    <h3 class="drawer-section-title">Input</h3>
                    <pre class="code-block">{{ json_encode($selectedCase->input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </section>

                <section class="drawer-section">
                    <h3 class="drawer-section-title">Output</h3>
                    @php
                        $output = (string) ($selectedCase->output ?? '');
                        $isLong = strlen($output) > 500;
                        $preview = $isLong ? substr($output, 0, 500) : $output;
                    @endphp
                    @if ($output === '')
                        <span class="dim">&mdash;</span>
                    @elseif ($isLong)
                        <div x-data="{ open: false }">
                            <pre class="code-block" x-show="!open" x-cloak>{{ $preview }}&hellip;</pre>
                            <pre class="code-block" x-show="open" x-cloak>{{ $output }}</pre>
                            <button type="button" class="drawer-link" x-on:click="open = !open">
                                <span x-text="open ? 'Show less' : 'Show more'">Show more</span>
                            </button>
                        </div>
                    @else
                        <pre class="code-block">{{ $output }}</pre>
                    @endif
                </section>

                @if ($selectedCase->expected !== null)
                    <section class="drawer-section">
                        <h3 class="drawer-section-title">Expected</h3>
                        <pre class="code-block">{{ json_encode($selectedCase->expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    </section>
                @endif

                <section class="drawer-section">
                    <h3 class="drawer-section-title">Assertions</h3>
                    @php
                        $assertions = $selectedCase->assertion_results ?? [];
                    @endphp
                    @if (empty($assertions))
                        <span class="dim">No assertions recorded.</span>
                    @else
                        <ul class="assertion-list">
                            @foreach ($assertions as $index => $assertion)
                                @php
                                    $assertionName = is_array($assertion) && isset($assertion['name']) ? (string) $assertion['name'] : 'assertion';
                                    $assertionPassed = is_array($assertion) && isset($assertion['passed']) ? (bool) $assertion['passed'] : false;
                                    $assertionReason = is_array($assertion) && isset($assertion['reason']) ? (string) $assertion['reason'] : '';
                                    $assertionScore = is_array($assertion) && isset($assertion['score']) ? $assertion['score'] : null;
                                    $assertionMeta = is_array($assertion) && isset($assertion['metadata']) && is_array($assertion['metadata']) ? $assertion['metadata'] : [];
                                @endphp
                                <li class="assertion-row" wire:key="assertion-{{ $selectedCase->id }}-{{ $index }}">
                                    <div class="assertion-head">
                                        @if ($assertionPassed)
                                            <span class="badge badge-pass">PASS</span>
                                        @else
                                            <span class="badge badge-fail">FAIL</span>
                                        @endif
                                        <span class="assertion-name">{{ $assertionName }}</span>
                                        @if ($assertionScore !== null)
                                            <span class="muted">score: {{ is_numeric($assertionScore) ? number_format((float) $assertionScore, 3) : (string) $assertionScore }}</span>
                                        @endif
                                    </div>
                                    @if ($assertionReason !== '')
                                        <div class="assertion-reason muted">{{ $assertionReason }}</div>
                                    @endif
                                    @if (! empty($assertionMeta))
                                        <details class="assertion-meta">
                                            <summary>Metadata</summary>
                                            <pre class="code-block">{{ json_encode($assertionMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </details>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                @if ($selectedCase->error_class)
                    <section class="drawer-section">
                        <h3 class="drawer-section-title">Error</h3>
                        <div class="error-box">
                            <div class="error-class">{{ $selectedCase->error_class }}</div>
                            <div class="error-message">{{ $selectedCase->error_message }}</div>
                            @if ($selectedCase->error_trace)
                                <details>
                                    <summary>Trace</summary>
                                    <pre class="code-block">{{ $selectedCase->error_trace }}</pre>
                                </details>
                            @endif
                        </div>
                    </section>
                @endif

                <section class="drawer-section">
                    <h3 class="drawer-section-title">Metadata</h3>
                    <dl class="meta-list">
                        <div>
                            <dt>Latency</dt>
                            <dd>
                                @if ($selectedCase->latency_ms !== null)
                                    {{ number_format($selectedCase->latency_ms, 1) }} ms
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Tokens in</dt>
                            <dd>
                                @if ($selectedCase->tokens_in !== null)
                                    {{ $selectedCase->tokens_in }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Tokens out</dt>
                            <dd>
                                @if ($selectedCase->tokens_out !== null)
                                    {{ $selectedCase->tokens_out }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Cost</dt>
                            <dd>
                                @if ($selectedCase->cost_usd !== null)
                                    ${{ number_format($selectedCase->cost_usd, 6) }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Model</dt>
                            <dd>{{ $selectedCase->model ?? '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="drawer-section" x-data="{
                    copied: false,
                    payload: @js($selectedCase->toArray()),
                    async copy() {
                        try {
                            await navigator.clipboard.writeText(JSON.stringify(this.payload, null, 2));
                            this.copied = true;
                            setTimeout(() => this.copied = false, 1500);
                        } catch (e) {}
                    }
                }">
                    <button type="button" class="drawer-link" x-on:click="copy">
                        <span x-text="copied ? 'Copied!' : 'Copy JSON'">Copy JSON</span>
                    </button>
                </section>
            </div>
        </aside>
    @endif
</div>
