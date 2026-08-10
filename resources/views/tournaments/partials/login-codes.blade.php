<div class="mt-6 mb-8 card border-2 border-success/40">
    <h2 class="card-title text-accent">Kod logowania na tablety</h2>
    <p class="card-description">
        Jeden wspólny kod dla tabletów i sędziowania w przeglądarce (laptop).
        Zeskanuj QR albo wpisz kod — potem możesz sędziować mecze tego turnieju.
        Widoczne tylko dla administratora.
    </p>

    @if (!empty($loginCode) && !empty($loginUrl))
        <div class="flex flex-wrap items-start gap-6 mt-4">
            <div class="bg-white p-3 rounded-lg inline-block">
                <img
                    src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&amp;data={{ urlencode($loginUrl) }}"
                    width="180"
                    height="180"
                    alt="QR logowania tabletu do turnieju"
                >
            </div>
            <div class="flex-1 min-w-[180px]">
                <p class="text-text-muted text-sm mb-1">Kod</p>
                <p class="font-mono text-2xl font-bold tracking-widest text-accent mb-3">{{ $loginCode }}</p>
                <p class="text-text-muted text-xs break-all mb-4">{{ $loginUrl }}</p>
                <form action="{{ route('tournaments.tablet-login-code.regenerate', $tournamentId) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-mini">Nowy kod</button>
                </form>
                <p class="text-text-secondary text-xs mt-3">
                    Regeneracja unieważnia poprzedni kod i sesje zalogowanych tabletów.
                </p>
            </div>
        </div>
    @else
        <p class="text-text-secondary text-sm mt-2">Brak aktywnego kodu logowania.</p>
    @endif
</div>
