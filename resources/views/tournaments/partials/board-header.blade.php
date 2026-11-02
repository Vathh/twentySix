<header class="board-header">
    <div class="board-heading">
        <p class="board-brand">twentySix</p>
        <h1 class="board-title">{{ $tournament->name }}</h1>
        <span class="board-phase">{{ $phaseLabel }}</span>
    </div>
    @if($showConnection)
        <span class="board-live" x-text="connectionLabel()">Łączenie…</span>
    @endif
</header>
