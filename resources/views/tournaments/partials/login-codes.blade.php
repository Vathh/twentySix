<div class="mt-6 mb-8 card border-2 border-success/40">
    <h2 class="card-title text-accent">Kody logowania na tablety</h2>
    <p class="card-description">
        Jeden kod = jeden tablet. Wpisz kod w aplikacji mobilnej, aby wpisywać wyniki meczów.
        Ta sekcja jest widoczna tylko dla administratora turnieju.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach ($loginCodes as $index => $code)
            <div class="flex items-center justify-between gap-3 bg-bg/60 border border-success/30 rounded-lg px-4 py-3">
                <span class="text-text-muted text-sm">Tablet {{ $index + 1 }}</span>
                <span class="font-mono text-xl font-bold tracking-widest text-accent">{{ $code }}</span>
            </div>
        @endforeach
    </div>
</div>
