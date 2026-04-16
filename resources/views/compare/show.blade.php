<div>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('proofread.runs.index') }}">Runs</a>
        <span class="breadcrumb-sep" aria-hidden="true">/</span>
        <span class="breadcrumb-current">Compare</span>
    </nav>

    <div class="run-header">
        <div class="run-header-main">
            <h1 class="page-title">
                @if ($state === 'ok' && $delta !== null)
                    Compare runs: {{ $delta->datasetName }}
                @else
                    Compare runs
                @endif
            </h1>
        </div>
        <p class="page-subtitle">Side-by-side diff of two eval runs of the same dataset.</p>
    </div>

    @if ($state === 'base_missing')
        <div class="table-wrapper">
            <div class="empty-state">
                <h3>Base run not found</h3>
                <p>The base run ID provided in the URL does not exist. Pick a different run below.</p>
            </div>
        </div>
    @elseif ($state === 'head_missing')
        <div class="table-wrapper">
            <div class="empty-state">
                <h3>Head run not found</h3>
                <p>The head run ID provided in the URL does not exist. Pick a different run below.</p>
            </div>
        </div>
    @elseif ($state === 'dataset_mismatch')
        <div class="table-wrapper">
            <div class="empty-state">
                <h3>Runs belong to different datasets</h3>
                <p class="muted">
                    Base dataset: <code>{{ $base?->dataset_name }}</code>
                    &middot;
                    Head dataset: <code>{{ $head?->dataset_name }}</code>
                </p>
                <p>Comparison is only supported across runs of the same dataset.</p>
            </div>
        </div>
    @elseif ($state === 'picker')
        <div class="table-wrapper">
            <div class="compare-picker">
                <h3>Select two runs</h3>
                <p class="muted">Choose a base and a head run to compare. Pick two runs of the same dataset.</p>

                <form method="get" action="{{ route('proofread.compare') }}" class="compare-picker-form">
                    <div class="filter-field grow">
                        <label for="base-select">Base run</label>
                        <select id="base-select" name="base" required>
                            <option value="">-- select --</option>
                            @foreach ($runOptions as $datasetName => $runs)
                                <optgroup label="{{ $datasetName }}">
                                    @foreach ($runs as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-field grow">
                        <label for="head-select">Head run</label>
                        <select id="head-select" name="head" required>
                            <option value="">-- select --</option>
                            @foreach ($runOptions as $datasetName => $runs)
                                <optgroup label="{{ $datasetName }}">
                                    @foreach ($runs as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-field">
                        <label>&nbsp;</label>
                        <button type="submit" class="drawer-link">Compare</button>
                    </div>
                </form>

                @if ($runOptions === [])
                    <p class="muted">No runs available yet. Persist a run with <code>php artisan evals:run --persist</code>.</p>
                @endif
            </div>
        </div>
    @elseif ($state === 'ok' && $delta !== null && $base !== null && $head !== null)
        <div class="compare-meta-grid">
            <div class="compare-meta-col">
                <span class="stat-card-title">Base</span>
                <div class="compare-meta-id">{{ $base->id }}</div>
                <div class="muted">
                    @if ($base->created_at)
                        {{ $base->created_at->format('Y-m-d H:i:s') }}
                    @endif
                </div>
                <dl class="meta-list">
                    <div>
                        <dt>Passed</dt>
                        <dd>{{ $base->pass_count }} / {{ $base->total_count }}</dd>
                    </div>
                    <div>
                        <dt>Cost</dt>
                        <dd>
                            @if ($base->total_cost_usd !== null)
                                ${{ number_format($base->total_cost_usd, 4) }}
                            @else
                                <span class="dim">&mdash;</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Duration</dt>
                        <dd>{{ number_format($base->duration_ms, 1) }} ms</dd>
                    </div>
                    <div>
                        <dt>Model</dt>
                        <dd>{{ $base->model ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Commit</dt>
                        <dd>{{ $base->commit_sha ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="compare-meta-col">
                <span class="stat-card-title">Head</span>
                <div class="compare-meta-id">{{ $head->id }}</div>
                <div class="muted">
                    @if ($head->created_at)
                        {{ $head->created_at->format('Y-m-d H:i:s') }}
                    @endif
                </div>
                <dl class="meta-list">
                    <div>
                        <dt>Passed</dt>
                        <dd>{{ $head->pass_count }} / {{ $head->total_count }}</dd>
                    </div>
                    <div>
                        <dt>Cost</dt>
                        <dd>
                            @if ($head->total_cost_usd !== null)
                                ${{ number_format($head->total_cost_usd, 4) }}
                            @else
                                <span class="dim">&mdash;</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Duration</dt>
                        <dd>{{ number_format($head->duration_ms, 1) }} ms</dd>
                    </div>
                    <div>
                        <dt>Model</dt>
                        <dd>{{ $head->model ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Commit</dt>
                        <dd>{{ $head->commit_sha ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-tile">
                <span class="summary-tile-label">Regressions</span>
                <span class="summary-tile-value {{ $delta->regressions > 0 ? 'fail' : '' }}">{{ $delta->regressions }}</span>
            </div>
            <div class="summary-tile">
                <span class="summary-tile-label">Improvements</span>
                <span class="summary-tile-value {{ $delta->improvements > 0 ? 'pass' : '' }}">{{ $delta->improvements }}</span>
            </div>
            <div class="summary-tile">
                <span class="summary-tile-label">Stable passes</span>
                <span class="summary-tile-value">{{ $delta->stablePasses }}</span>
            </div>
            <div class="summary-tile">
                <span class="summary-tile-label">Stable failures</span>
                <span class="summary-tile-value">{{ $delta->stableFailures }}</span>
            </div>
            <div class="summary-tile">
                <span class="summary-tile-label">Cost delta</span>
                @php
                    $costSign = $delta->costDeltaUsd >= 0 ? '+' : '-';
                    $costClass = $delta->costDeltaUsd > 0 ? 'fail' : ($delta->costDeltaUsd < 0 ? 'pass' : '');
                @endphp
                <span class="summary-tile-value delta-value {{ $costClass }}">{{ $costSign }}${{ number_format(abs($delta->costDeltaUsd), 4) }}</span>
            </div>
            <div class="summary-tile">
                <span class="summary-tile-label">Duration delta</span>
                @php
                    $durSign = $delta->durationDeltaMs >= 0 ? '+' : '-';
                    $durClass = $delta->durationDeltaMs > 0 ? 'fail' : ($delta->durationDeltaMs < 0 ? 'pass' : '');
                @endphp
                <span class="summary-tile-value delta-value {{ $durClass }}">{{ $durSign }}{{ number_format(abs($delta->durationDeltaMs), 0) }} ms</span>
            </div>
        </div>

        <div class="filter-tabs" role="tablist" aria-label="Case status filter">
            <button type="button" role="tab" class="filter-tab {{ $statusFilter === null || $statusFilter === '' ? 'active' : '' }}" wire:click="setStatusFilter(null)">
                All <span class="filter-tab-count">{{ $counts['all'] }}</span>
            </button>
            <button type="button" role="tab" class="filter-tab {{ $statusFilter === 'regression' ? 'active' : '' }}" wire:click="setStatusFilter('regression')">
                Regressions <span class="filter-tab-count">{{ $counts['regression'] }}</span>
            </button>
            <button type="button" role="tab" class="filter-tab {{ $statusFilter === 'improvement' ? 'active' : '' }}" wire:click="setStatusFilter('improvement')">
                Improvements <span class="filter-tab-count">{{ $counts['improvement'] }}</span>
            </button>
            <button type="button" role="tab" class="filter-tab {{ $statusFilter === 'stable' ? 'active' : '' }}" wire:click="setStatusFilter('stable')">
                Stable <span class="filter-tab-count">{{ $counts['stable'] }}</span>
            </button>
        </div>

        @if ($delta->regressions === 0 && $delta->improvements === 0 && $delta->totalCases === ($delta->stablePasses + $delta->stableFailures))
            <div class="table-wrapper">
                <div class="empty-state">
                    <h3>No changes detected</h3>
                    <p>Both runs produced identical pass/fail outcomes for every case.</p>
                </div>
            </div>
        @elseif (count($filteredCases) === 0)
            <div class="table-wrapper">
                <div class="empty-state">
                    <h3>No cases match the selected filter</h3>
                    <p>Try switching to a different tab above.</p>
                </div>
            </div>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Case</th>
                            <th>Base</th>
                            <th>Head</th>
                            <th>New / fixed failures</th>
                            <th>Cost &Delta;</th>
                            <th>Duration &Delta;</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filteredCases as $case)
                            @php
                                $statusClass = 'status-'.str_replace('_', '-', $case->status);
                                $label = $case->caseName ?? 'Case '.$case->caseIndex;

                                $caseCost = null;
                                if ($case->baseCostUsd !== null && $case->headCostUsd !== null) {
                                    $caseCost = $case->headCostUsd - $case->baseCostUsd;
                                }

                                $caseDur = null;
                                if ($case->baseDurationMs !== null && $case->headDurationMs !== null) {
                                    $caseDur = $case->headDurationMs - $case->baseDurationMs;
                                }
                            @endphp
                            <tr wire:key="case-{{ $case->caseIndex }}" wire:click="selectCase({{ $case->caseIndex }})">
                                <td>
                                    <span class="compare-status {{ $statusClass }}">
                                        @switch($case->status)
                                            @case('regression') Regression @break
                                            @case('improvement') Improvement @break
                                            @case('stable_pass') Stable PASS @break
                                            @case('stable_fail') Stable FAIL @break
                                            @case('base_only') Gone @break
                                            @case('head_only') New @break
                                            @default {{ $case->status }}
                                        @endswitch
                                    </span>
                                </td>
                                <td>
                                    <span class="muted">#{{ $case->caseIndex }}</span>
                                    <span>{{ $label }}</span>
                                </td>
                                <td>
                                    @if ($case->basePassed)
                                        <span class="badge badge-pass">PASS</span>
                                    @else
                                        <span class="badge badge-fail">FAIL</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($case->headPassed)
                                        <span class="badge badge-pass">PASS</span>
                                    @else
                                        <span class="badge badge-fail">FAIL</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($case->newFailures !== [])
                                        <div class="compare-failures fail">
                                            new: {{ implode(', ', $case->newFailures) }}
                                        </div>
                                    @endif
                                    @if ($case->fixedFailures !== [])
                                        <div class="compare-failures pass">
                                            fixed: {{ implode(', ', $case->fixedFailures) }}
                                        </div>
                                    @endif
                                    @if ($case->newFailures === [] && $case->fixedFailures === [])
                                        <span class="dim">&mdash;</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($caseCost === null)
                                        <span class="dim">&mdash;</span>
                                    @else
                                        @php
                                            $cs = $caseCost >= 0 ? '+' : '-';
                                            $cc = $caseCost > 0 ? 'fail' : ($caseCost < 0 ? 'pass' : '');
                                        @endphp
                                        <span class="delta-value {{ $cc }}">{{ $cs }}${{ number_format(abs($caseCost), 6) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($caseDur === null)
                                        <span class="dim">&mdash;</span>
                                    @else
                                        @php
                                            $ds = $caseDur >= 0 ? '+' : '-';
                                            $dc = $caseDur > 0 ? 'fail' : ($caseDur < 0 ? 'pass' : '');
                                        @endphp
                                        <span class="delta-value {{ $dc }}">{{ $ds }}{{ number_format(abs($caseDur), 1) }} ms</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="compare-actions">
            <a class="drawer-link" href="{{ route('proofread.runs.index') }}">Back to runs</a>
        </div>

        @if ($selectedCaseIndex !== null)
            <div
                x-data
                class="drawer-overlay"
                wire:click="closeCase"
            ></div>
            <aside
                x-data="{}"
                class="drawer case-drawer-compare"
                role="dialog"
                aria-label="Case comparison"
            >
                <div class="drawer-header">
                    <div>
                        <div class="drawer-title">
                            Case #{{ $selectedCaseIndex }}
                            @if ($headCase?->case_name ?? $baseCase?->case_name)
                                — {{ $headCase?->case_name ?? $baseCase?->case_name }}
                            @endif
                        </div>
                    </div>
                    <button type="button" class="drawer-close" wire:click="closeCase" aria-label="Close">
                        &times;
                    </button>
                </div>

                <div class="drawer-body">
                    <section class="drawer-section">
                        <h3 class="drawer-section-title">Input</h3>
                        @if ($baseCase !== null)
                            <pre class="code-block">{{ json_encode($baseCase->input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        @elseif ($headCase !== null)
                            <pre class="code-block">{{ json_encode($headCase->input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        @else
                            <span class="dim">&mdash;</span>
                        @endif
                    </section>

                    <section class="drawer-section">
                        <h3 class="drawer-section-title">Outputs</h3>
                        <div class="compare-outputs">
                            <div>
                                <div class="muted">Base</div>
                                @if ($baseCase === null)
                                    <div class="dim">missing in base</div>
                                @elseif ((string) ($baseCase->output ?? '') === '')
                                    <span class="dim">&mdash;</span>
                                @else
                                    <pre class="code-block">{{ (string) $baseCase->output }}</pre>
                                @endif
                            </div>
                            <div>
                                <div class="muted">Head</div>
                                @if ($headCase === null)
                                    <div class="dim">missing in head</div>
                                @elseif ((string) ($headCase->output ?? '') === '')
                                    <span class="dim">&mdash;</span>
                                @else
                                    <pre class="code-block">{{ (string) $headCase->output }}</pre>
                                @endif
                            </div>
                        </div>
                    </section>

                    <section class="drawer-section">
                        <h3 class="drawer-section-title">Assertions</h3>
                        <div class="compare-outputs">
                            <div>
                                <div class="muted">Base</div>
                                @include('proofread::compare.partials.assertions', ['assertions' => $baseCase?->assertion_results ?? []])
                            </div>
                            <div>
                                <div class="muted">Head</div>
                                @include('proofread::compare.partials.assertions', ['assertions' => $headCase?->assertion_results ?? []])
                            </div>
                        </div>
                    </section>
                </div>
            </aside>
        @endif
    @endif
</div>
