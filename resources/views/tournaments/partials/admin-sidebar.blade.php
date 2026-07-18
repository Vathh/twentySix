<aside class="w-72 backdrop-blur bg-white/5 border-r border-white/10 p-6 flex flex-col">
    <h2 class="text-light-green font-bold text-lg mb-6 tracking-wide">⚙️ Zarządzanie turniejem</h2>

    <nav class="flex flex-col space-y-3">
        @if(!$tournament->isStarted())
            <a href="{{ route('tournaments.start', $tournament->id) }}"
               class="flex items-center gap-3 bg-white/10 hover:bg-white/15 px-4 py-3 rounded-lg transition">
                ➕ Rozpocznij turniej
            </a>
        @endif
        <a href="{{ route('tournaments.admins', $tournament->id) }}"
           class="flex items-center gap-3 bg-white/10 hover:bg-white/15 px-4 py-3 rounded-lg transition">
            👥 Administratorzy
        </a>
    </nav>
</aside>
