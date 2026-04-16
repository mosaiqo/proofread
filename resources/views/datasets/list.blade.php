<div>
    <h1 class="page-title">Datasets</h1>
    <p class="page-subtitle">All datasets with run history, avg cost, duration, and 30-day pass rate.</p>

    @if (empty($datasets))
        <div class="table-wrapper">
            <div class="empty-state">
                <h3>No datasets yet</h3>
                <p>Datasets will appear here after your first run with <code>--persist</code>.</p>
            </div>
        </div>
    @else
        <div class="dataset-grid">
            @foreach ($datasets as $row)
                @php
                    $avgRate = null;
                    $samples = array_filter($row['pass_history'], fn ($v) => $v !== null);
                    if (! empty($samples)) {
                        $avgRate = array_sum($samples) / count($samples);
                    }

                    $colorClass = 'sparkline-neutral';
                    if ($avgRate !== null) {
                        if ($avgRate >= 0.95) {
                            $colorClass = 'sparkline-pass';
                        } elseif ($avgRate < 0.75) {
                            $colorClass = 'sparkline-fail';
                        } else {
                            $colorClass = 'sparkline-warn';
                        }
                    }
                @endphp

                <a
                    href="{{ route('proofread.runs.index', ['dataset' => $row['name']]) }}"
                    class="dataset-card"
                    wire:key="dataset-{{ $row['model']->id }}"
                >
                    <div class="dataset-card-head">
                        <div class="dataset-name">{{ $row['name'] }}</div>
                        <div class="muted dataset-card-sub">
                            {{ $row['case_count'] }} cases &middot; {{ $row['runs_count'] }} runs
                        </div>
                    </div>

                    <div class="sparkline-wrap {{ $colorClass }}">
                        <x-proofread::sparkline :data="$row['pass_history']" />
                    </div>

                    <div class="dataset-meta-row">
                        <span class="muted">
                            @if ($row['avg_cost'] !== null)
                                Avg ${{ number_format($row['avg_cost'], 4) }}
                            @else
                                <span class="dim">Avg &mdash;</span>
                            @endif
                        </span>
                        <span class="muted">
                            @if ($row['avg_duration'] !== null)
                                {{ number_format($row['avg_duration'], 0) }} ms
                            @else
                                <span class="dim">&mdash;</span>
                            @endif
                        </span>
                        <span class="muted">
                            @if ($row['last_run_at'] !== null)
                                {{ $row['last_run_at']->diffForHumans() }}
                            @else
                                <span class="dim">no runs</span>
                            @endif
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
