@php
    $overlayUrl = route('games.overlay', ['type' => $kind, 'id' => $gameId]);
@endphp
<div
    class="flex flex-wrap items-center gap-2"
    x-data="{ overlayCopied: false }"
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
    <p class="w-full text-text-muted text-xs leading-snug">
        OBS: Źródło → Przeglądarka, 1920×1080, przezroczyste tło.
        Na drugi monitor dodaj <span class="score-num">?bg=solid</span>.
    </p>
</div>
