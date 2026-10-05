@props([
    'items' => [],
    'order' => 'asc',
])

@php
    $counts = [];
    foreach ($items as $item) {
        $value = (int) (is_object($item) ? ($item->value ?? 0) : $item);
        if ($value <= 0) {
            continue;
        }
        $counts[$value] = ($counts[$value] ?? 0) + 1;
    }

    if ($order === 'desc') {
        krsort($counts, SORT_NUMERIC);
    } else {
        ksort($counts, SORT_NUMERIC);
    }
@endphp

@if($counts === [])
    <span class="text-text-muted">—</span>
@else
    <span class="achievement-marks">
        @foreach($counts as $value => $count)
            <span class="achievement-mark">
                <span class="score-num">{{ $value }}</span>
                @if($count > 1)
                    <span class="achievement-mark-count">× {{ $count }}</span>
                @endif
            </span>
        @endforeach
    </span>
@endif
