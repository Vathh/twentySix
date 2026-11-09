@php
    $locked = ($item['levelName'] ?? 'locked') === 'locked';
    $ahead = (bool) ($ahead ?? false);
    $alt = $item['label'].($item['levelLabel'] ? ' — '.$item['levelLabel'] : '');
@endphp
<article @class([
    'compare-card',
    'is-locked' => $locked,
    'is-ahead' => $ahead,
])>
    <div class="compare-card__art">
        <x-badge-artwork
            :src="$item['image']"
            :alt="$alt"
            :locked="$locked"
        />
    </div>
    <p class="compare-card__metal">{{ $item['levelLabel'] ?: 'Zablokowane' }}</p>
    <p class="compare-card__meta">
        <span>Poziom {{ $item['levelNumber'] }}</span>
        <span>{{ $item['progressLabel'] }}</span>
    </p>
    <div
        class="compare-card__bar"
        role="progressbar"
        aria-valuenow="{{ $item['progressPercent'] }}"
        aria-valuemin="0"
        aria-valuemax="100"
        aria-label="{{ $item['label'] }}: {{ $item['progressLabel'] }}"
    >
        <span style="width: {{ (int) $item['progressPercent'] }}%"></span>
    </div>
</article>
