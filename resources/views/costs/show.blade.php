<div>
    <h1 class="page-title">Costs</h1>
    <p class="page-subtitle">Spend breakdown by model and dataset, with daily cost trend.</p>

    <div class="filter-tabs">
        <button
            type="button"
            class="filter-tab {{ $windowFilter === '7d' ? 'active' : '' }}"
            wire:click="$set('windowFilter', '7d')"
        >
            Last 7 days
            <span class="filter-tab-count">{{ $windowCounts['7d'] }}</span>
        </button>
        <button
            type="button"
            class="filter-tab {{ $windowFilter === '30d' ? 'active' : '' }}"
            wire:click="$set('windowFilter', '30d')"
        >
            Last 30 days
            <span class="filter-tab-count">{{ $windowCounts['30d'] }}</span>
        </button>
        <button
            type="button"
            class="filter-tab {{ $windowFilter === 'all' ? 'active' : '' }}"
            wire:click="$set('windowFilter', 'all')"
        >
            All time
            <span class="filter-tab-count">{{ $windowCounts['all'] }}</span>
        </button>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-card-title">Total cost</span>
            <span class="stat-card-value currency-value">${{ number_format($totalCost, 4) }}</span>
            <span class="stat-card-subtitle">Across runs in the window</span>
        </div>

        <div class="stat-card">
            <span class="stat-card-title">Total runs</span>
            <span class="stat-card-value">{{ $totalRuns }}</span>
            <span class="stat-card-subtitle">Runs counted in the window</span>
        </div>

        <div class="stat-card">
            <span class="stat-card-title">Avg cost per run</span>
            @if ($avgCostPerRun === null)
                <span class="stat-card-value muted">&mdash;</span>
                <span class="stat-card-subtitle">No runs with cost data</span>
            @else
                <span class="stat-card-value currency-value">${{ number_format($avgCostPerRun, 4) }}</span>
                <span class="stat-card-subtitle">Mean across billed runs</span>
            @endif
        </div>

        <div class="stat-card">
            <span class="stat-card-title">Most expensive model</span>
            @if ($mostExpensiveModel === null)
                <span class="stat-card-value muted">&mdash;</span>
                <span class="stat-card-subtitle">No model usage yet</span>
            @else
                <span class="stat-card-value">{{ $mostExpensiveModel }}</span>
                <span class="stat-card-subtitle">Highest total spend</span>
            @endif
        </div>
    </div>

    <div class="chart-card">
        <h2 class="section-title">Daily cost &mdash; {{ $windowFilter === '7d' ? 'last 7 days' : 'last 30 days' }}</h2>
        <x-proofread::trend-chart
            :data="$dailyTrend"
            y-format="currency"
            aria-label="Daily cost trend"
        />
    </div>

    <div class="overview-grid">
        <div class="overview-panel">
            <h2 class="section-title">Cost by model</h2>
            @if (empty($byModel))
                <div class="overview-empty">
                    <span class="muted">No billed runs in this window.</span>
                </div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Model</th>
                                <th>Runs</th>
                                <th>Tokens</th>
                                <th>Total</th>
                                <th>Avg</th>
                                <th>Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byModel as $row)
                                <tr wire:key="cost-model-{{ $row['model'] }}">
                                    <td>{{ $row['model'] }}</td>
                                    <td>{{ $row['runs'] }}</td>
                                    <td>{{ number_format($row['total_tokens']) }}</td>
                                    <td class="currency-value">${{ number_format($row['total_cost'], 4) }}</td>
                                    <td class="currency-value">${{ number_format($row['avg_cost'], 4) }}</td>
                                    <td>
                                        <div class="cost-bar">
                                            <div class="cost-percentage-track">
                                                <div
                                                    class="cost-percentage-fill"
                                                    style="width: {{ number_format($row['percentage'] * 100, 2) }}%"
                                                ></div>
                                            </div>
                                            <span class="muted">{{ number_format($row['percentage'] * 100, 1) }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="overview-panel">
            <h2 class="section-title">Cost by dataset</h2>
            @if (empty($byDataset))
                <div class="overview-empty">
                    <span class="muted">No billed runs in this window.</span>
                </div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Dataset</th>
                                <th>Runs</th>
                                <th>Total</th>
                                <th>Avg</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byDataset as $row)
                                <tr
                                    wire:key="cost-dataset-{{ $row['dataset_name'] }}"
                                    onclick="window.location='{{ route('proofread.runs.index', ['dataset' => $row['dataset_name']]) }}'"
                                >
                                    <td>
                                        <a href="{{ route('proofread.runs.index', ['dataset' => $row['dataset_name']]) }}">{{ $row['dataset_name'] }}</a>
                                    </td>
                                    <td>{{ $row['runs'] }}</td>
                                    <td class="currency-value">${{ number_format($row['total_cost'], 4) }}</td>
                                    <td class="currency-value">${{ number_format($row['avg_cost'], 4) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
