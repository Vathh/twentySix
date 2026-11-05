<aside class="admin-sidebar">
    <h2 class="admin-sidebar-title">⚙️ Zarządzanie turniejem</h2>

    <nav class="flex flex-col space-y-3">
        @if(!$tournament->isStarted())
            <div
                x-data
                x-init="$store.tournamentStartLive.participantCount = {{ (int) ($participantCount ?? 0) }}; $store.tournamentStartLive.minPlayers = {{ (int) ($minPlayers ?? 4) }}"
            >
                <a
                    href="{{ route('tournaments.start', $tournament->id) }}"
                    class="admin-sidebar-link"
                    x-show="$store.tournamentStartLive.participantCount >= $store.tournamentStartLive.minPlayers"
                    x-cloak
                >
                    ➕ Wystartuj
                </a>
                <span
                    class="admin-sidebar-link opacity-50 cursor-not-allowed"
                    aria-disabled="true"
                    x-show="$store.tournamentStartLive.participantCount < $store.tournamentStartLive.minPlayers"
                    x-cloak
                >
                    ➕ Wystartuj
                </span>
                <p
                    class="text-text-secondary text-xs"
                    x-show="$store.tournamentStartLive.participantCount < $store.tournamentStartLive.minPlayers"
                    x-cloak
                >
                    Potrzeba co najmniej {{ $minPlayers ?? 4 }} osób w składzie.
                </p>
            </div>
        @elseif($tournament->canCancelPlay())
            @if($tournament->format !== \App\Enums\TournamentFormat::DoubleElimination)
                <a href="{{ route('tournaments.playoff-formats', $tournament->id) }}" class="admin-sidebar-link">
                    Format rund
                </a>
            @endif
            <button type="button" data-cancel-play class="admin-sidebar-link w-full text-left text-danger" @click="cancelOpen = true">
                Anuluj rozgrywki
            </button>
        @endif
        <a href="{{ route('tournaments.admins', $tournament->id) }}" class="admin-sidebar-link">
            👥 Administratorzy
        </a>
        <x-delete-application-entity
            :action="route('tournaments.destroy', $tournament->id)"
            :name="$tournament->name"
            label="turniej"
            hint="Turniej zniknie z aplikacji. Mecze i wyniki zostają w bazie przez 90 dni, potem są kasowane. Przywrócić może operator platformy."
        />
    </nav>
</aside>
