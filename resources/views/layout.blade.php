<!DOCTYPE html>
<html lang="en" class="proofread">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Proofread' }}</title>
    <style>@include('proofread::partials.styles')</style>
    <script defer src="https://unpkg.com/alpinejs@3.14.8/dist/cdn.min.js"></script>
    @livewireStyles
</head>
<body>
    <nav class="proofread-nav">
        <div class="proofread-nav-inner">
            <a href="{{ route('proofread.runs.index') }}" class="proofread-logo">Proofread</a>
            <div class="proofread-nav-links">
                <a href="{{ route('proofread.runs.index') }}" class="{{ request()->routeIs('proofread.runs.*') ? 'active' : '' }}">Runs</a>
                <a href="{{ route('proofread.datasets.index') }}" class="{{ request()->routeIs('proofread.datasets.*') ? 'active' : '' }}">Datasets</a>
            </div>
        </div>
    </nav>

    <main class="proofread-main">
        {{ $slot ?? '' }}
    </main>

    @livewireScripts
</body>
</html>
