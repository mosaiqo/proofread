<div wire:poll.5s.keep-alive>
    <h1 class="page-title">Comparisons</h1>
    <p class="page-subtitle">Multi-subject eval comparisons across models, prompts, or configurations.</p>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-card-title">Total comparisons</span>
            <span class="stat-card-value">{{ $stats['total'] }}</span>
            <span class="stat-card-subtitle">All persisted comparisons</span>
        </div>

        <div class="stat-card">
            <span class="stat-card-title">Pass rate (7 days)</span>
            @if ($stats['seven_day_pass_rate'] === null)
                <span class="stat-card-value muted">&mdash;</span>
                <span class="stat-card-subtitle">No comparisons this week</span>
            @else
                <span class="stat-card-value {{ $stats['seven_day_pass_rate'] >= 0.95 ? 'pass' : ($stats['seven_day_pass_rate'] < 0.75 ? 'fail' : '') }}">
                    {{ number_format($stats['seven_day_pass_rate'] * 100, 1) }}%
                </span>
                <span class="stat-card-subtitle">Fully-passing comparisons, last 7 days</span>
            @endif
        </div>

        <div class="stat-card">
            <span class="stat-card-title">Active datasets</span>
            <span class="stat-card-value">{{ $stats['active_datasets'] }}</span>
            <span class="stat-card-subtitle">Datasets compared in the last 30 days</span>
        </div>
    </div>

    <div class="filters">
        <div class="filter-field">
            <label for="comparison-dataset-filter">Dataset</label>
            <select id="comparison-dataset-filter" wire:model.live="datasetFilter">
                <option value="">All datasets</option>
                @foreach ($datasetOptions as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-field">
            <label for="comparison-status-filter">Status</label>
            <select id="comparison-status-filter" wire:model.live="statusFilter">
                <option value="">All</option>
                <option value="passed">Passed</option>
                <option value="failed">Failed</option>
            </select>
        </div>

        <div class="filter-field grow">
            <label for="comparison-search">Search</label>
            <input
                id="comparison-search"
                type="search"
                placeholder="Filter by name or dataset..."
                wire:model.live.debounce.400ms="search"
            >
        </div>
    </div>

    @if ($comparisons->total() === 0)
        <div class="table-wrapper">
            <div class="empty-state">
                <h3>No comparisons yet</h3>
                <p>Comparisons appear here after you run <code>php artisan evals:compare-providers --persist</code>.</p>
            </div>
        </div>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Dataset</th>
                        <th>Subjects</th>
                        <th>Runs passed</th>
                        <th>Cost</th>
                        <th>Duration</th>
                        <th>When</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($comparisons as $comparison)
                        @php
                            $subjects = (array) $comparison->subject_labels;
                            $visible = array_slice($subjects, 0, 3);
                            $overflow = max(0, count($subjects) - count($visible));
                            $allPassed = $comparison->passed_runs === $comparison->total_runs;
                        @endphp
                        <tr
                            wire:key="comparison-{{ $comparison->id }}"
                            class="comparison-row"
                            onclick="window.location='{{ route('proofread.comparisons.show', $comparison) }}'"
                        >
                            <td>
                                <a href="{{ route('proofread.comparisons.show', $comparison) }}">{{ $comparison->name }}</a>
                            </td>
                            <td>
                                <span class="muted">{{ $comparison->dataset_name }}</span>
                            </td>
                            <td>
                                <div class="subject-pills">
                                    @foreach ($visible as $label)
                                        <span class="subject-pill">{{ $label }}</span>
                                    @endforeach
                                    @if ($overflow > 0)
                                        <span class="subject-pill subject-pill-more">+{{ $overflow }} more</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if ($allPassed)
                                    <span class="badge badge-pass">{{ $comparison->passed_runs }} / {{ $comparison->total_runs }}</span>
                                @else
                                    <span class="badge badge-fail">{{ $comparison->passed_runs }} / {{ $comparison->total_runs }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($comparison->total_cost_usd !== null)
                                    ${{ number_format($comparison->total_cost_usd, 4) }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </td>
                            <td>{{ number_format($comparison->duration_ms, 1) }} ms</td>
                            <td>
                                <span class="muted">{{ $comparison->created_at?->diffForHumans() }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($comparisons->hasPages())
                <div class="pagination-wrap">
                    {{ $comparisons->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
