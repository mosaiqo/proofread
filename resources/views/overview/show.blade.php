<div>
    <h1 class="page-title">Overview</h1>
    <p class="page-subtitle">Evaluation activity at a glance.</p>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-card-title">Total runs</span>
            <span class="stat-card-value">{{ $globalStats['total_runs'] }}</span>
            <span class="stat-card-subtitle">All persisted runs</span>
        </div>

        <div class="stat-card">
            <span class="stat-card-title">Total cost</span>
            <span class="stat-card-value">${{ number_format($globalStats['total_cost_usd'], 4) }}</span>
            <span class="stat-card-subtitle">Across all runs</span>
        </div>

        <div class="stat-card">
            <span class="stat-card-title">Pass rate (7 days)</span>
            @if ($globalStats['seven_day_pass_rate'] === null)
                <span class="stat-card-value muted">&mdash;</span>
                <span class="stat-card-subtitle">No runs in the last week</span>
            @else
                <span class="stat-card-value {{ $globalStats['seven_day_pass_rate'] >= 0.95 ? 'pass' : ($globalStats['seven_day_pass_rate'] < 0.75 ? 'fail' : '') }}">
                    {{ number_format($globalStats['seven_day_pass_rate'] * 100, 1) }}%
                </span>
                <span class="stat-card-subtitle">Runs in the last 7 days</span>
            @endif
        </div>

        <div class="stat-card">
            <span class="stat-card-title">Active datasets</span>
            <span class="stat-card-value">{{ $globalStats['active_datasets'] }}</span>
            <span class="stat-card-subtitle">With runs in the last 30 days</span>
        </div>
    </div>

    <div class="chart-card">
        <h2 class="section-title">Pass rate &mdash; last 30 days</h2>
        <x-proofread::trend-chart :data="$passRateTrend" />
    </div>

    <div class="overview-grid">
        <div class="overview-panel">
            <h2 class="section-title">Top failing datasets</h2>
            @if (empty($topFailingDatasets))
                <div class="overview-empty">
                    <span class="muted">No failures in the last 7 days.</span>
                </div>
            @else
                <ul class="failing-list">
                    @foreach ($topFailingDatasets as $row)
                        <li class="failing-row">
                            <div class="failing-row-main">
                                <a href="{{ route('proofread.runs.index', ['dataset' => $row['name']]) }}">
                                    {{ $row['name'] }}
                                </a>
                                <span class="muted">{{ $row['fail_count'] }} / {{ $row['total_count'] }} failed</span>
                            </div>
                            <span class="fail-rate">{{ number_format($row['fail_rate'] * 100, 1) }}%</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="overview-panel">
            <h2 class="section-title">Recent regressions</h2>
            @if (empty($recentRegressions))
                <div class="overview-empty">
                    <span class="muted">No regressions detected.</span>
                </div>
            @else
                <ul class="regressions-list">
                    @foreach ($recentRegressions as $reg)
                        <li class="regressions-row">
                            <div class="failing-row-main">
                                <a href="{{ route('proofread.compare', ['base' => $reg['base_id'], 'head' => $reg['head_id']]) }}">
                                    {{ $reg['dataset_name'] }}
                                </a>
                                <span class="muted">
                                    {{ number_format($reg['base_pass_rate'] * 100, 1) }}%
                                    &rarr;
                                    {{ number_format($reg['head_pass_rate'] * 100, 1) }}%
                                </span>
                            </div>
                            <span class="fail-rate">
                                {{ number_format($reg['delta'] * 100, 1) }}%
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="overview-panel">
        <h2 class="section-title">Recent runs</h2>
        @if ($recentRuns->isEmpty())
            <div class="overview-empty">
                <span class="muted">No runs yet.</span>
            </div>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Dataset</th>
                            <th>Result</th>
                            <th>Pass rate</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentRuns as $run)
                            <tr
                                wire:key="recent-run-{{ $run->id }}"
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
                                <td>
                                    <span class="muted">{{ $run->created_at?->diffForHumans() }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
