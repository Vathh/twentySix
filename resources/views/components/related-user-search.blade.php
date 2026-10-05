@props([
    'searchUrl',
    'addUrl',
    'removeUrl' => '',
    'cancelUrlTemplate' => '',
    'related' => [],
    'pending' => [],
    'addLabel' => 'Zaproś',
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
    <form @submit.prevent="search()" class="people-add">
        <input
            type="text"
            x-model="query"
            placeholder="Szukaj konta, min. {{ $minChars }} znaków"
            class="input-field"
            autocomplete="off"
        >
        <button type="submit" class="btn btn-primary" :disabled="loading">
            <span x-text="loading ? 'Szukam…' : 'Szukaj'"></span>
        </button>
    </form>

    <div class="people-results" x-show="searched" x-cloak>
        <p class="text-sm text-text-muted" x-show="results.length === 0">Brak wyników wyszukiwania.</p>
        <div class="people-list" x-show="results.length > 0">
            <template x-for="user in results" :key="user.id">
                <div class="people-row">
                    <span class="people-row-name" x-text="user.name"></span>
                    <button
                        type="button"
                        class="btn-mini"
                        :disabled="busyKey === ('add-' + user.id)"
                        @click="add(user)"
                        x-text="addLabel"
                    ></button>
                </div>
            </template>
        </div>
    </div>

    <div x-show="related.length === 0 && pending.length === 0" x-cloak>
        <x-empty-state
            class="!py-10"
            title="Pula jest pusta"
            :description="$emptyRelated"
        />
    </div>

    <div class="people-catalog" x-show="rosterGroups.length > 0" x-cloak>
        <template x-for="group in rosterGroups" :key="group.letter">
            <section class="people-letter">
                <p class="people-letter-label" x-text="group.letter"></p>
                <div class="people-list">
                    <template x-for="person in group.people" :key="person.key">
                        <div class="people-row">
                            <span class="people-row-name" x-text="person.name"></span>
                            <span class="people-row-aside">
                                <span class="people-row-status" x-show="person.pending">Oczekuje</span>
                                <button
                                    type="button"
                                    class="people-x"
                                    :aria-label="person.pending ? 'Anuluj' : 'Usuń'"
                                    :disabled="busyKey === ((person.pending ? 'cancel-' : 'remove-') + person.id)"
                                    @click="person.pending ? cancel(person) : remove(person)"
                                >
                                    <svg class="people-x-icon" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                        <path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                                    </svg>
                                </button>
                            </span>
                        </div>
                    </template>
                </div>
            </section>
        </template>
    </div>
</div>
