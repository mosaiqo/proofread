<div wire:poll.5s.keep-alive>
    <h1 class="page-title">Runs</h1>
    <p class="page-subtitle">Eval runs history, filterable by dataset and status.</p>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-card-title">Last 24h pass rate</span>
            @if ($stats['last_24h_pass_rate'] === null)
                <span class="stat-card-value muted">&mdash;</span>
                <span class="stat-card-subtitle">No runs yet</span>
            @else
                <span class="stat-card-value {{ $stats['last_24h_pass_rate'] >= 0.95 ? 'pass' : ($stats['last_24h_pass_rate'] < 0.75 ? 'fail' : '') }}">
                    {{ number_format($stats['last_24h_pass_rate'] * 100, 1) }}%
                </span>
                <span class="stat-card-subtitle">Across runs in the past day</span>
            @endif
        </div>

        <div class="stat-card">
            <span class="stat-card-title">This week</span>
            @if ($stats['this_week_pass_rate'] === null)
                <span class="stat-card-value muted">&mdash;</span>
                <span class="stat-card-subtitle">No runs this week</span>
            @else
                <span class="stat-card-value {{ $stats['this_week_pass_rate'] >= 0.95 ? 'pass' : ($stats['this_week_pass_rate'] < 0.75 ? 'fail' : '') }}">
                    {{ number_format($stats['this_week_pass_rate'] * 100, 1) }}%
                </span>
                <span class="stat-card-subtitle">Pass rate over the last 7 days</span>
            @endif
        </div>

        <div class="stat-card">
            <span class="stat-card-title">All-time</span>
            <span class="stat-card-value">{{ $stats['total_runs'] }}</span>
            <span class="stat-card-subtitle">Runs &middot; ${{ number_format($stats['total_cost_usd'], 4) }} total cost</span>
        </div>
    </div>

    <div class="filters">
        <div class="filter-field">
            <label for="dataset-filter">Dataset</label>
            <select id="dataset-filter" wire:model.live="datasetFilter">
                <option value="">All datasets</option>
                @foreach ($datasetOptions as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-field">
            <label for="status-filter">Status</label>
            <select id="status-filter" wire:model.live="statusFilter">
                <option value="">All</option>
                <option value="passed">Passed</option>
                <option value="failed">Failed</option>
            </select>
        </div>

        <div class="filter-field grow">
            <label for="search-filter">Search</label>
            <input
                id="search-filter"
                type="search"
                placeholder="Filter by dataset name..."
                wire:model.live.debounce.400ms="search"
            >
        </div>
    </div>

    @if ($runs->total() === 0)
        <div class="table-wrapper">
            <div class="empty-state">
                <h3>No runs yet</h3>
                <p>Runs will appear here after you execute <code>php artisan evals:run --persist</code>.</p>
            </div>
        </div>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dataset</th>
                        <th>Result</th>
                        <th>Pass rate</th>
                        <th>Duration</th>
                        <th>Cost</th>
                        <th>When</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr
                            wire:key="run-{{ $run->id }}"
                            onclick="window.location='{{ route('proofread.runs.show', $run) }}'"
                        >
                            <td>
                                <a href="{{ route('proofread.runs.show', $run) }}">{{ $run->dataset_name }}</a>
                            </td>
                            <td>
                                @if ($run->passed)
                                    <span class="badge badge-pass">Passed</span>
                                @else
                                    <span class="badge badge-fail">Failed</span>
                                @endif
                            </td>
                            <td>
                                <span class="muted">{{ $run->pass_count }} / {{ $run->total_count }}</span>
                            </td>
                            <td>{{ number_format($run->duration_ms, 1) }} ms</td>
                            <td>
                                @if ($run->total_cost_usd !== null)
                                    ${{ number_format($run->total_cost_usd, 4) }}
                                @else
                                    <span class="dim">&mdash;</span>
                                @endif
                            </td>
                            <td>
                                <span class="muted">{{ $run->created_at?->diffForHumans() }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($runs->hasPages())
                <div class="pagination-wrap">
                    {{ $runs->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
