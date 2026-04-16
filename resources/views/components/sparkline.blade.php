@props([
    'data' => [],
    'width' => 120,
    'height' => 24,
])

@php
    $values = is_array($data) ? array_values($data) : [];
    $count = count($values);
    $hasAny = false;
    foreach ($values as $v) {
        if ($v !== null) {
            $hasAny = true;
            break;
        }
    }
@endphp

@if (! $hasAny || $count === 0)
    <span class="sparkline-empty dim">&mdash;</span>
@else
    @php
        $segments = [];
        $points = [];
        $penDown = false;
        $xStep = $count > 1 ? ($width / ($count - 1)) : 0;

        foreach ($values as $i => $raw) {
            if ($raw === null) {
                $penDown = false;
                continue;
            }

            $clamped = max(0.0, min(1.0, (float) $raw));
            $x = $count > 1 ? ($i * $xStep) : ($width / 2);
            $y = $height - ($clamped * $height);
            $x = round($x, 2);
            $y = round($y, 2);

            if (! $penDown) {
                $segments[] = 'M'.$x.' '.$y;
                $penDown = true;
            } else {
                $segments[] = 'L'.$x.' '.$y;
            }

            $points[] = ['x' => $x, 'y' => $y];
        }

        $path = implode(' ', $segments);
    @endphp

    <svg
        class="sparkline"
        viewBox="0 0 {{ $width }} {{ $height }}"
        width="{{ $width }}"
        height="{{ $height }}"
        role="img"
        aria-label="Pass rate over 30 days"
    >
        <path d="{{ $path }}" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
        @foreach ($points as $p)
            <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="1.5" fill="currentColor" />
        @endforeach
    </svg>
@endif
