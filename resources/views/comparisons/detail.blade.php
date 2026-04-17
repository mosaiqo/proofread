<div>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('proofread.comparisons.index') }}">Comparisons</a>
        <span class="breadcrumb-sep" aria-hidden="true">/</span>
        <span class="breadcrumb-current">{{ $summary['name'] }}</span>
    </nav>

    <div class="comparison-header">
        <div class="run-header-main">
            <h1 class="page-title">{{ $summary['name'] }}</h1>
            @if ($summary['all_passed'])
                <span class="badge badge-pass">Passed</span>
            @else
                <span class="badge badge-fail">Failed</span>
            @endif
        </div>
        <div class="run-header-meta muted">
            <span><a href="{{ route('proofread.datasets.index') }}">{{ $summary['dataset_name'] }}</a></span>
            @if ($summary['dataset_version_id'])
                <span class="sep">&middot;</span>
                <span>version {{ $summary['dataset_version_id'] }}</span>
            @endif
            <span class="sep">&middot;</span>
            <span>{{ $summary['passed_runs'] }} / {{ $summary['total_runs'] }} runs passed</span>
            <span class="sep">&middot;</span>
            <span>{{ number_format($summary['duration_ms'], 1) }} ms</span>
            @if ($summary['total_cost_usd'] !== null)
                <span class="sep">&middot;</span>
                <span>${{ number_format($summary['total_cost_usd'], 4) }}</span>
            @endif
            @if ($summary['commit_sha'])
                <span class="sep">&middot;</span>
                <span>{{ $summary['commit_sha'] }}</span>
            @endif
            @if ($summary['created_at_formatted'])
                <span class="sep">&middot;</span>
                <span>{{ $summary['created_at_formatted'] }}</span>
                <span class="dim">({{ $summary['created_at_human'] }})</span>
            @endif
        </div>
    </div>

    <div class="winner-grid">
        <div class="winner-card">
            <span class="stat-card-title">Best pass rate</span>
            @if ($winners['best_pass_rate'] === null)
                <span class="stat-card-value muted">&mdash;</span>
                <span class="stat-card-subtitle">No runs recorded</span>
            @else
                <div class="winner-card-row">
                    <span class="subject-pill">{{ $winners['best_pass_rate']['subject_label'] }}</span>
                    <span class="stat-card-value">{{ number_format($winners['best_pass_rate']['pass_rate'] * 100, 1) }}%</span>
                </div>
            @endif
        </div>

        <div class="winner-card">
            <span class="stat-card-title">Cheapest</span>
            @if ($winners['cheapest'] === null)
                <span class="stat-card-value muted">&mdash;</span>
                <span class="stat-card-subtitle">No cost data recorded</span>
            @else
                <div class="winner-card-row">
                    <span class="subject-pill">{{ $winners['cheapest']['subject_label'] }}</span>
                    <span class="stat-card-value">${{ number_format($winners['cheapest']['cost_usd'], 4) }}</span>
                </div>
            @endif
        </div>

        <div class="winner-card">
            <span class="stat-card-title">Fastest</span>
            @if ($winners['fastest'] === null)
                <span class="stat-card-value muted">&mdash;</span>
                <span class="stat-card-subtitle">No runs recorded</span>
            @else
                <div class="winner-card-row">
                    <span class="subject-pill">{{ $winners['fastest']['subject_label'] }}</span>
                    <span class="stat-card-value">{{ number_format($winners['fastest']['duration_ms'], 1) }} ms</span>
                </div>
            @endif
        </div>
    </div>

    @if (count($matrix['cases']) === 0)
        <div class="table-wrapper">
            <div class="empty-state">
                <h3>No matrix to show</h3>
                <p>This comparison has no case results yet.</p>
            </div>
        </div>
    @else
        <div class="table-wrapper">
            <table class="table matrix-table">
                <thead>
                    <tr>
                        <th>Case</th>
                        @foreach ($matrix['subjects'] as $subjectLabel)
                            <th>{{ $subjectLabel }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($matrix['cases'] as $case)
                        <tr wire:key="matrix-case-{{ $case['case_index'] }}">
                            <td>
                                <span class="muted">#{{ $case['case_index'] }}</span>
                                <span>{{ $case['case_name'] ?? 'Case '.$case['case_index'] }}</span>
                            </td>
                            @foreach ($matrix['subjects'] as $subjectLabel)
                                @php
                                    $cell = $case['cells'][$subjectLabel] ?? null;
                                    $cellClass = 'matrix-cell';
                                    $label = '—';
                                    if ($cell !== null) {
                                        if ($cell->error_class) {
                                            $cellClass .= ' matrix-cell-err';
                                            $label = 'ERR';
                                        } elseif ($cell->passed) {
                                            $cellClass .= ' matrix-cell-pass';
                                            $label = 'PASS';
                                        } else {
                                            $cellClass .= ' matrix-cell-fail';
                                            $label = 'FAIL';
                                        }
                                    }
                                @endphp
                                <td
                                    class="{{ $cellClass }}"
                                    @if ($cell !== null)
                                        wire:click="selectCell('{{ $subjectLabel }}', {{ $case['case_index'] }})"
                                    @endif
                                >
                                    @if ($cell === null)
                                        <span class="dim">&mdash;</span>
                                    @else
                                        @if ($cell->error_class)
                                            <span class="badge badge-warning">ERR</span>
                                        @elseif ($cell->passed)
                                            <span class="badge badge-pass">PASS</span>
                                        @else
                                            <span class="badge badge-fail">FAIL</span>
                                        @endif
                                        <div class="matrix-cell-meta muted">
                                            @if ($cell->latency_ms !== null)
                                                {{ number_format($cell->latency_ms, 0) }} ms
                                            @endif
                                            @if ($cell->cost_usd !== null)
                                                @if ($cell->latency_ms !== null)
                                                    <span class="sep">&middot;</span>
                                                @endif
                                                ${{ number_format($cell->cost_usd, 4) }}
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr class="matrix-footer-row">
                        <td>
                            <strong>Pass rate</strong>
                        </td>
                        @foreach ($matrix['subjects'] as $subjectLabel)
                            @php $agg = $matrix['aggregates'][$subjectLabel]; @endphp
                            <td>
                                @if ($agg['pass_rate'] === null)
                                    <span class="dim">&mdash;</span>
                                @else
                                    {{ number_format($agg['pass_rate'] * 100, 1) }}%
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr class="matrix-footer-row">
                        <td>
                            <strong>Cost</strong>
                        </td>
                        @foreach ($matrix['subjects'] as $subjectLabel)
                            @php $agg = $matrix['aggregates'][$subjectLabel]; @endphp
                            <td>
                                @if ($agg['total_cost_usd'] === null)
                                    <span class="dim">&mdash;</span>
                                @else
                                    ${{ number_format($agg['total_cost_usd'], 4) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr class="matrix-footer-row">
                        <td>
                            <strong>Avg latency</strong>
                        </td>
                        @foreach ($matrix['subjects'] as $subjectLabel)
                            @php $agg = $matrix['aggregates'][$subjectLabel]; @endphp
                            <td>
                                @if ($agg['avg_latency_ms'] === null)
                                    <span class="dim">&mdash;</span>
                                @else
                                    {{ number_format($agg['avg_latency_ms'], 1) }} ms
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr class="matrix-footer-row">
                        <td>
                            <strong>Total tokens</strong>
                        </td>
                        @foreach ($matrix['subjects'] as $subjectLabel)
                            @php $agg = $matrix['aggregates'][$subjectLabel]; @endphp
                            <td>
                                {{ number_format($agg['total_tokens']) }}
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    <div class="compare-actions">
        <a class="drawer-link" href="{{ route('proofread.comparisons.index') }}">Back to list</a>
        <a class="button" href="{{ route('proofread.comparisons.export', ['comparison' => $comparison->id, 'format' => 'md']) }}">Export Markdown</a>
        <a class="button" href="{{ route('proofread.comparisons.export', ['comparison' => $comparison->id, 'format' => 'html']) }}">Export HTML</a>
    </div>

    @if ($selectedCell !== null)
        @php
            $result = $selectedCell['result'];
            $run = $selectedCell['run'];
            $subjectLabel = $selectedCell['subject_label'];
        @endphp
        <div
            x-data
            class="drawer-overlay"
            wire:click="closeCell"
        ></div>
        <aside
            x-data="{}"
            class="drawer cell-drawer"
            role="dialog"
            aria-label="Cell details"
        >
            <div class="drawer-header">
                <div>
                    <div class="drawer-title">
                        {{ $subjectLabel }} &middot; Case #{{ $result->case_index }}
                        @if ($result->case_name)
                            — {{ $result->case_name }}
                        @endif
                    </div>
                </div>
                <button type="button" class="drawer-close" wire:click="closeCell" aria-label="Close">
                    &times;
                </button>
            </div>

            <div class="drawer-body">
                <section class="drawer-section">
                    <h3 class="drawer-section-title">Status</h3>
                    <div>
                        @if ($result->error_class)
                            <span class="badge badge-warning">ERR</span>
                        @elseif ($result->passed)
                            <span class="badge badge-pass">PASS</span>
                        @else
                            <span class="badge badge-fail">FAIL</span>
                        @endif
                    </div>
                </section>

                <section class="drawer-section">
                    <h3 class="drawer-section-title">Input</h3>
                    <pre class="code-block">{{ json_encode($result->input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </section>

                <section class="drawer-section">
                    <h3 class="drawer-section-title">Output</h3>
                    @if ((string) ($result->output ?? '') === '')
                        <span class="dim">&mdash;</span>
                    @else
                        <pre class="code-block">{{ (string) $result->output }}</pre>
                    @endif
                </section>

                <section class="drawer-section">
                    <h3 class="drawer-section-title">Assertions</h3>
                    @php
                        $assertions = $result->assertion_results ?? [];
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
                                @endphp
                                <li class="assertion-row" wire:key="cmp-assertion-{{ $result->id }}-{{ $index }}">
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
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                @if ($result->error_class)
                    <section class="drawer-section">
                        <h3 class="drawer-section-title">Error</h3>
                        <div class="error-box">
                            <div class="error-class">{{ $result->error_class }}</div>
                            <div class="error-message">{{ $result->error_message }}</div>
                            @if ($result->error_trace)
                                <details>
                                    <summary>Trace</summary>
                                    <pre class="code-block">{{ $result->error_trace }}</pre>
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
                                @if ($result->latency_ms !== null)
                                    {{ number_format($result->latency_ms, 1) }} ms
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Tokens in</dt>
                            <dd>
                                @if ($result->tokens_in !== null)
                                    {{ $result->tokens_in }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Tokens out</dt>
                            <dd>
                                @if ($result->tokens_out !== null)
                                    {{ $result->tokens_out }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Cost</dt>
                            <dd>
                                @if ($result->cost_usd !== null)
                                    ${{ number_format($result->cost_usd, 6) }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Model</dt>
                            <dd>{{ $result->model ?? '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="drawer-section">
                    <a class="drawer-link" href="{{ route('proofread.runs.show', $run) }}">View full run</a>
                </section>
            </div>
        </aside>
    @endif
</div>
