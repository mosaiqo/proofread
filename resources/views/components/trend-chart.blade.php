@props([
    'data' => [],
    'width' => 800,
    'height' => 140,
    'padding' => 32,
])

@php
    $count = count($data);
    $points = [];
    $i = 0;
    foreach ($data as $day => $value) {
        if ($value !== null) {
            $clamped = max(0.0, min(1.0, (float) $value));
            $x = $padding + ($count > 1 ? ($i / ($count - 1)) * ($width - 2 * $padding) : 0);
            $y = $height - $padding - $clamped * ($height - 2 * $padding);
            $points[] = [
                'x' => round($x, 1),
                'y' => round($y, 1),
                'day' => $day,
                'value' => $clamped,
            ];
        }
        $i++;
    }

    $segments = [];
    foreach ($points as $j => $p) {
        $segments[] = ($j === 0 ? 'M ' : 'L ').$p['x'].' '.$p['y'];
    }
    $path = implode(' ', $segments);

    $yTicks = [0.0, 0.25, 0.5, 0.75, 1.0];
    $firstLabel = $count > 0 ? (string) array_key_first($data) : '';
    $lastLabel = $count > 0 ? (string) array_key_last($data) : '';
@endphp

<svg
    class="trend-chart"
    viewBox="0 0 {{ $width }} {{ $height }}"
    width="100%"
    height="{{ $height }}"
    role="img"
    aria-label="Pass rate trend over the last 30 days"
>
    @foreach ($yTicks as $t)
        @php $y = $height - $padding - $t * ($height - 2 * $padding); @endphp
        <line
            x1="{{ $padding }}"
            y1="{{ $y }}"
            x2="{{ $width - $padding }}"
            y2="{{ $y }}"
            class="trend-gridline"
        />
        <text x="{{ $padding - 6 }}" y="{{ $y + 3 }}" text-anchor="end" class="trend-axis-label">
            {{ (int) round($t * 100) }}%
        </text>
    @endforeach

    @if ($firstLabel !== '')
        <text x="{{ $padding }}" y="{{ $height - 8 }}" class="trend-axis-label">{{ $firstLabel }}</text>
    @endif
    @if ($lastLabel !== '' && $lastLabel !== $firstLabel)
        <text
            x="{{ $width - $padding }}"
            y="{{ $height - 8 }}"
            text-anchor="end"
            class="trend-axis-label"
        >{{ $lastLabel }}</text>
    @endif

    @if ($path !== '')
        <path d="{{ $path }}" class="trend-line" fill="none" />
    @endif

    @foreach ($points as $p)
        <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="2.5" class="trend-point">
            <title>{{ $p['day'] }}: {{ number_format($p['value'] * 100, 1) }}%</title>
        </circle>
    @endforeach

    @if (empty($points))
        <text
            x="{{ $width / 2 }}"
            y="{{ $height / 2 }}"
            text-anchor="middle"
            class="trend-axis-label"
        >No data in the last 30 days</text>
    @endif
</svg>
