@props([
    'data' => [],
    'width' => 800,
    'height' => 140,
    'padding' => 32,
    'yFormat' => 'percentage',
    'yMax' => null,
    'ariaLabel' => null,
])

@php
    $count = count($data);
    $format = $yFormat === 'currency' ? 'currency' : 'percentage';

    $numericValues = array_filter(
        array_map(static fn ($v) => $v === null ? null : (float) $v, $data),
        static fn ($v): bool => $v !== null,
    );

    if ($format === 'currency') {
        if ($yMax !== null) {
            $scaleMax = (float) $yMax;
        } else {
            $peak = empty($numericValues) ? 0.0 : max($numericValues);
            $scaleMax = $peak > 0 ? $peak * 1.1 : 1.0;
        }
        $scaleMin = 0.0;
    } else {
        $scaleMax = $yMax !== null ? (float) $yMax : 1.0;
        $scaleMin = 0.0;
    }

    if ($scaleMax <= $scaleMin) {
        $scaleMax = $scaleMin + 1.0;
    }

    $points = [];
    $i = 0;
    foreach ($data as $day => $value) {
        if ($value !== null) {
            $numeric = (float) $value;
            $clamped = max($scaleMin, min($scaleMax, $numeric));
            $normalized = ($clamped - $scaleMin) / ($scaleMax - $scaleMin);
            $x = $padding + ($count > 1 ? ($i / ($count - 1)) * ($width - 2 * $padding) : 0);
            $y = $height - $padding - $normalized * ($height - 2 * $padding);
            $points[] = [
                'x' => round($x, 1),
                'y' => round($y, 1),
                'day' => $day,
                'value' => $numeric,
            ];
        }
        $i++;
    }

    $segments = [];
    foreach ($points as $j => $p) {
        $segments[] = ($j === 0 ? 'M ' : 'L ').$p['x'].' '.$p['y'];
    }
    $path = implode(' ', $segments);

    $tickFractions = [0.0, 0.25, 0.5, 0.75, 1.0];
    $firstLabel = $count > 0 ? (string) array_key_first($data) : '';
    $lastLabel = $count > 0 ? (string) array_key_last($data) : '';

    $formatTick = static function (float $fraction) use ($format, $scaleMin, $scaleMax): string {
        $value = $scaleMin + $fraction * ($scaleMax - $scaleMin);

        if ($format === 'currency') {
            return '$'.number_format($value, $value < 1 ? 4 : 2);
        }

        return (string) ((int) round($value * 100)).'%';
    };

    $formatPoint = static function (float $value) use ($format): string {
        if ($format === 'currency') {
            return '$'.number_format($value, 4);
        }

        return number_format($value * 100, 1).'%';
    };

    $resolvedAriaLabel = $ariaLabel
        ?? ($format === 'currency'
            ? 'Cost trend over the last '.max($count, 0).' days'
            : 'Pass rate trend over the last 30 days');
@endphp

<svg
    class="trend-chart"
    viewBox="0 0 {{ $width }} {{ $height }}"
    width="100%"
    height="{{ $height }}"
    role="img"
    aria-label="{{ $resolvedAriaLabel }}"
>
    @foreach ($tickFractions as $t)
        @php $y = $height - $padding - $t * ($height - 2 * $padding); @endphp
        <line
            x1="{{ $padding }}"
            y1="{{ $y }}"
            x2="{{ $width - $padding }}"
            y2="{{ $y }}"
            class="trend-gridline"
        />
        <text x="{{ $padding - 6 }}" y="{{ $y + 3 }}" text-anchor="end" class="trend-axis-label">
            {{ $formatTick($t) }}
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
            <title>{{ $p['day'] }}: {{ $formatPoint($p['value']) }}</title>
        </circle>
    @endforeach

    @if (empty($points))
        <text
            x="{{ $width / 2 }}"
            y="{{ $height / 2 }}"
            text-anchor="middle"
            class="trend-axis-label"
        >No data in the selected window</text>
    @endif
</svg>
