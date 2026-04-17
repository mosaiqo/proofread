@php
    use Mosaiqo\Proofread\Models\EvalRun;

    $since = now()->subDay();

    $stats = EvalRun::query()
        ->where('created_at', '>=', $since)
        ->selectRaw('COUNT(*) as total, SUM(passed) as passed, SUM(total_cost_usd) as cost, AVG(duration_ms) as avg_duration')
        ->first();

    $total = (int) ($stats->total ?? 0);
    $passed = (int) ($stats->passed ?? 0);
    $passRate = $total > 0 ? round(($passed / $total) * 100, 1) : 0.0;
    $cost = (float) ($stats->cost ?? 0.0);
    $avgDuration = (float) ($stats->avg_duration ?? 0.0);

    $recent = EvalRun::query()
        ->orderByDesc('created_at')
        ->limit(5)
        ->get();
@endphp

<x-pulse::card :cols="$cols ?? '4'" :rows="$rows ?? '2'" :class="$class ?? ''">
    <x-pulse::card-header
        name="Proofread Evals"
        details="past 24h"
    >
        <x-slot:icon>
            <x-pulse::icons.sparkles />
        </x-slot:icon>
    </x-pulse::card-header>

    <div class="grid grid-cols-4 gap-4 p-4 text-sm">
        <div>
            <div class="text-gray-500 dark:text-gray-400">Runs</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($total) }}</div>
        </div>
        <div>
            <div class="text-gray-500 dark:text-gray-400">Pass rate</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $passRate }}%</div>
        </div>
        <div>
            <div class="text-gray-500 dark:text-gray-400">Cost</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($cost, 4) }}</div>
        </div>
        <div>
            <div class="text-gray-500 dark:text-gray-400">Avg duration</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($avgDuration) }}ms</div>
        </div>
    </div>

    <x-pulse::scroll :expand="$expand ?? false">
        @if ($recent->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Dataset</x-pulse::th>
                        <x-pulse::th>Status</x-pulse::th>
                        <x-pulse::th class="text-right">Cost</x-pulse::th>
                        <x-pulse::th class="text-right">When</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($recent as $run)
                        <tr wire:key="proofread-run-{{ $run->id }}-spacer" class="h-2 first:h-0"></tr>
                        <tr wire:key="proofread-run-{{ $run->id }}">
                            <x-pulse::td>
                                <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $run->dataset_name }}">
                                    {{ $run->dataset_name }}
                                </code>
                            </x-pulse::td>
                            <x-pulse::td>
                                @if ($run->passed)
                                    <span class="inline-block rounded bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100 px-2 py-0.5 text-xs">passed</span>
                                @else
                                    <span class="inline-block rounded bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-100 px-2 py-0.5 text-xs">failed</span>
                                @endif
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">
                                @if ($run->total_cost_usd === null)
                                    <span class="text-gray-400">—</span>
                                @else
                                    ${{ number_format((float) $run->total_cost_usd, 4) }}
                                @endif
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-500 dark:text-gray-400">
                                {{ $run->created_at?->diffForHumans() }}
                            </x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
