@php
    $overlayUrl = route('games.overlay', ['type' => $kind, 'id' => $gameId]);
    $tickerAvailable = in_array($kind, ['group', 'playoff'], true);
    $tickerUrl = $tickerAvailable
        ? route('games.overlay-ticker', ['type' => $kind, 'id' => $gameId])
        : null;
@endphp
<div
    class="flex flex-wrap items-center gap-2"
    x-data="{ overlayCopied: false, tickerCopied: false }"
>
    <button
        type="button"
        class="px-3 py-1 rounded text-xs font-semibold border border-accent text-accent hover:bg-accent/10 transition"
        x-on:click="
            navigator.clipboard.writeText(@js($overlayUrl)).then(() => {
                overlayCopied = true;
                setTimeout(() => overlayCopied = false, 2000);
            })
        "
    >
        <span x-text="overlayCopied ? 'Skopiowano' : 'Kopiuj link overlay'">Kopiuj link overlay</span>
    </button>
    <a href="{{ $overlayUrl }}"
       class="px-3 py-1 rounded text-xs font-semibold border border-border text-text-muted hover:text-accent hover:border-accent/40 transition"
       target="_blank"
       rel="noopener noreferrer">
        Podgląd overlay
    </a>
    @if($tickerAvailable)
        <button
            type="button"
            class="px-3 py-1 rounded text-xs font-semibold border border-accent text-accent hover:bg-accent/10 transition"
            x-on:click="
                navigator.clipboard.writeText(@js($tickerUrl)).then(() => {
                    tickerCopied = true;
                    setTimeout(() => tickerCopied = false, 2000);
                })
            "
        >
            <span x-text="tickerCopied ? 'Skopiowano' : 'Kopiuj link paska'">Kopiuj link paska</span>
        </button>
        <a href="{{ $tickerUrl }}?bg=solid"
           class="px-3 py-1 rounded text-xs font-semibold border border-border text-text-muted hover:text-accent hover:border-accent/40 transition"
           target="_blank"
           rel="noopener noreferrer">
            Podgląd paska
        </a>
    @endif
    <p class="w-full text-text-muted text-xs leading-snug">
        Baner: OBS → źródło przeglądarki, 1920×1080, przezroczyste tło.
        @if($tickerAvailable)
            Pasek innych meczów: drugie źródło, 1920×56. Link paska pomija mecz, z którego go skopiowano.
        @endif
        Na drugi monitor dodaj <span class="score-num">?bg=solid</span>.
    </p>
</div>
