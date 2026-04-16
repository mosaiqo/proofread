@if (empty($assertions))
    <span class="dim">&mdash;</span>
@else
    <ul class="assertion-list">
        @foreach ($assertions as $assertion)
            @php
                $assertionName = is_array($assertion) && isset($assertion['name']) ? (string) $assertion['name'] : 'assertion';
                $assertionPassed = is_array($assertion) && isset($assertion['passed']) ? (bool) $assertion['passed'] : false;
                $assertionReason = is_array($assertion) && isset($assertion['reason']) ? (string) $assertion['reason'] : '';
            @endphp
            <li class="assertion-row">
                <div class="assertion-head">
                    @if ($assertionPassed)
                        <span class="badge badge-pass">PASS</span>
                    @else
                        <span class="badge badge-fail">FAIL</span>
                    @endif
                    <span class="assertion-name">{{ $assertionName }}</span>
                </div>
                @if ($assertionReason !== '')
                    <div class="assertion-reason muted">{{ $assertionReason }}</div>
                @endif
            </li>
        @endforeach
    </ul>
@endif
