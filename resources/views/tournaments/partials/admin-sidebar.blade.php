<aside class="admin-sidebar">
    <h2 class="admin-sidebar-title">⚙️ Zarządzanie turniejem</h2>

    <nav class="flex flex-col space-y-3">
        @if(!$tournament->isStarted())
            <a href="{{ route('tournaments.start', $tournament->id) }}" class="admin-sidebar-link">
                ➕ Rozpocznij turniej
            </a>
        @endif
        <a href="{{ route('tournaments.admins', $tournament->id) }}" class="admin-sidebar-link">
            👥 Administratorzy
        </a>
    </nav>
</aside>
