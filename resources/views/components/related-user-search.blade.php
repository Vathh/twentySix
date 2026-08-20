@props([
    'searchUrl',
    'addUrl',
    'removeUrl' => '',
    'cancelUrlTemplate' => '',
    'related' => [],
    'pending' => [],
    'addLabel' => 'Dodaj',
    'minChars' => 5,
    'emptyRelated' => 'Brak użytkowników powiązanych z tą pulą.',
])

<div
    x-data="relatedUserSearch(@js([
        'searchUrl' => $searchUrl,
        'addUrl' => $addUrl,
        'removeUrl' => $removeUrl,
        'cancelUrlTemplate' => $cancelUrlTemplate,
        'related' => $related,
        'pending' => $pending,
        'addLabel' => $addLabel,
        'minChars' => $minChars,
        'csrfToken' => csrf_token(),
    ]))"
>
    <div class="card mb-8">
        <h2 class="section-title text-accent">Aktualnie powiązani użytkownicy</h2>
        <p class="text-text-secondary" x-show="related.length === 0" x-cloak>{{ $emptyRelated }}</p>
        <div class="flex flex-wrap gap-3" x-show="related.length > 0" x-cloak>
            <template x-for="user in related" :key="user.id">
                <div class="tile flex items-center justify-center flex-col">
                    <span class="card-title mb-4 text-wrap text-center" x-text="user.name"></span>
                    <button
                        type="button"
                        class="btn-mini-danger"
                        :disabled="busyKey === ('remove-' + user.id)"
                        @click="remove(user)"
                    >Usuń</button>
                </div>
            </template>
        </div>
    </div>

    <div class="card mb-8" x-show="pending.length > 0" x-cloak>
        <h2 class="section-title text-accent">Oczekujące zaproszenia</h2>
        <div class="flex flex-wrap gap-3">
            <template x-for="invitation in pending" :key="invitation.id">
                <div class="tile flex items-center justify-center flex-col">
                    <span class="card-title mb-4 text-wrap text-center" x-text="invitation.name"></span>
                    <button
                        type="button"
                        class="btn-mini-danger"
                        :disabled="busyKey === ('cancel-' + invitation.id)"
                        @click="cancel(invitation)"
                    >Anuluj</button>
                </div>
            </template>
        </div>
    </div>

    <h2 class="section-title text-center">Wyszukiwanie użytkowników</h2>

    <form @submit.prevent="search()" class="mb-6 flex flex-wrap items-center gap-4">
        <input
            type="text"
            x-model="query"
            placeholder="Min. {{ $minChars }} znaków..."
            class="input-field flex-1 min-w-[200px]"
            autocomplete="off"
        >
        <button type="submit" class="btn btn-primary" :disabled="loading">
            <span x-text="loading ? 'Szukam…' : 'Szukaj'"></span>
        </button>
    </form>

    <p class="empty-state" x-show="searched && results.length === 0" x-cloak>
        Brak wyników wyszukiwania.
    </p>
    <div class="flex flex-wrap gap-3 justify-center" x-show="results.length > 0" x-cloak>
        <template x-for="user in results" :key="user.id">
            <div class="tile flex items-center justify-center flex-col bg-bg-elevated">
                <span class="card-title mb-4 text-wrap text-center" x-text="user.name"></span>
                <button
                    type="button"
                    class="btn btn-mini"
                    :disabled="busyKey === ('add-' + user.id)"
                    @click="add(user)"
                    x-text="addLabel"
                ></button>
            </div>
        </template>
    </div>
</div>
