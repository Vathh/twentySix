<div class="mt-6 mb-8 bg-lighter-bg border-2 border-light-green/40 p-6 rounded-xl shadow-lg">
    <h2 class="text-lg font-semibold text-light-green mb-2">Kody logowania na tablety</h2>
    <p class="text-light-white/80 text-sm mb-4">
        Jeden kod = jeden tablet. Wpisz kod w aplikacji mobilnej, aby wpisywać wyniki meczów.
        Ta sekcja jest widoczna tylko dla administratora turnieju.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach ($loginCodes as $index => $code)
            <div class="flex items-center justify-between gap-3 bg-dark-bg/60 border border-light-green/30 rounded-lg px-4 py-3">
                <span class="text-light-white/70 text-sm">Tablet {{ $index + 1 }}</span>
                <span class="font-mono text-xl font-bold tracking-widest text-light-orange">{{ $code }}</span>
            </div>
        @endforeach
    </div>
</div>
