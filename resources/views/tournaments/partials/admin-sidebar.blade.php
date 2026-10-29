<aside class="admin-sidebar">
    <h2 class="admin-sidebar-title">⚙️ Zarządzanie turniejem</h2>

    <nav class="flex flex-col space-y-3">
        @if(!$tournament->isStarted())
            @if(($participantCount ?? 0) >= ($minPlayers ?? 4))
                <a href="{{ route('tournaments.start', $tournament->id) }}" class="admin-sidebar-link">
                    ➕ Wystartuj
                </a>
            @else
                <span class="admin-sidebar-link opacity-50 cursor-not-allowed" aria-disabled="true">
                    ➕ Wystartuj
                </span>
                <p class="text-text-secondary text-xs">
                    Potrzeba co najmniej {{ $minPlayers ?? 4 }} osób w składzie.
                </p>
            @endif
        @elseif($tournament->canCancelPlay())
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
