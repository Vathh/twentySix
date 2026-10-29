        <div
            x-show="$store.tournamentStartLive.flash"
            x-cloak
            class="fixed top-5 right-5 z-50 w-96 max-w-[calc(100vw-2rem)] p-3 rounded-lg shadow-xl flex items-start justify-between gap-3 bg-bg-elevated"
            :class="$store.tournamentStartLive.flash?.type === 'error'
                ? 'border border-danger text-danger-text'
                : 'border border-success text-success-bright'"
        >
            <div x-text="$store.tournamentStartLive.flash?.message"></div>
            <button
                type="button"
                class="shrink-0 font-bold opacity-70 hover:opacity-100"
                @click="$store.tournamentStartLive.flash = null"
            >&times;</button>
        </div>

        {{-- Strefa 1: Uczestnicy turnieju --}}
        <div
            class="mb-8 card border-2 border-success/40"
            @if($canManageParticipants)
                x-data
                x-init="$store.tournamentStartLive.init(@js([
                    'participants' => $participantsLive ?? [],
                    'participantCount' => $participantCount,
                    'minPlayers' => $minPlayers,
                    'canManage' => true,
                    'csrfToken' => csrf_token(),
                    'inviteUrl' => route('tournaments.invitations.send', $tournament->id),
                    'joinSelfUrl' => route('tournaments.invitations.join-self', $tournament->id),
                    'currentUserId' => auth()->id(),
                    'invitationPipeline' => $invitationPipelineLive ?? [],
                ]))"
            @endif
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                <h2 class="section-title">Uczestnicy turnieju</h2>
                @if($canManageParticipants)
                    <span
                        class="text-sm font-semibold px-3 py-1 rounded-full shrink-0"
                        :class="$store.tournamentStartLive.participantCount >= $store.tournamentStartLive.minPlayers
                            ? 'bg-success-muted text-success-bright'
                            : 'bg-accent/20 text-accent'"
                        x-text="$store.tournamentStartLive.participantCount + ' / min. ' + $store.tournamentStartLive.minPlayers"
                    ></span>
                @else
                    <span @class([
                        'text-sm font-semibold px-3 py-1 rounded-full shrink-0',
                        'bg-success-muted text-success-bright' => $participantCount >= $minPlayers,
                        'bg-accent/20 text-accent' => $participantCount < $minPlayers,
                    ])>
                        {{ $participantCount }} / min. {{ $minPlayers }}
                    </span>
                @endif
            </div>
            <p class="text-text-secondary text-sm mb-3">
                W turnieju grają wszyscy z tej listy — zaakceptowane zaproszenia oraz goście dodani do turnieju.
            </p>

            @if($canManageParticipants)
                <p
                    class="text-text-secondary/80 italic text-sm"
                    x-show="$store.tournamentStartLive.participants.length === 0"
                >
                    Brak uczestników. Użyj sekcji poniżej, aby wysłać zaproszenia lub dodać gości.
                </p>
                <div x-show="$store.tournamentStartLive.participants.length > 0" x-cloak>
                    <p
                        class="text-accent/70 text-xs mb-2"
                        x-show="$store.tournamentStartLive.participants.length > 12"
                    >Duża lista — przewiń, aby zobaczyć wszystkich.</p>
                    <div class="max-h-36 sm:max-h-44 overflow-y-auto overflow-x-hidden pr-1 -mr-1">
                        <div class="flex flex-wrap gap-2">
                            <template x-for="p in $store.tournamentStartLive.participants" :key="(p.kind || '') + '-' + (p.invitationId || p.playerId)">
                                <div class="flex items-center gap-1 bg-bg text-text-secondary pl-3 pr-1 py-1.5 rounded-lg text-sm">
                                    <span>
                                        <span x-text="p.name"></span>
                                        <span
                                            class="text-xs text-accent ml-1"
                                            x-show="p.kind === 'guest'"
                                        >gość</span>
                                    </span>
                                    <button
                                        type="button"
                                        class="btn-mini-danger w-7 h-7 p-0 flex items-center justify-center font-bold"
                                        title="Usuń z turnieju"
                                        x-show="p.removeUrl"
                                        :disabled="$store.tournamentStartLive.busyKey === ((p.kind || '') + '-' + (p.invitationId || p.playerId))"
                                        @click="$store.tournamentStartLive.removeParticipant(p)"
                                    >×</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            @elseif($participants->isEmpty())
                <p class="text-text-secondary/80 italic text-sm">
                    Jeszcze nikt nie jest w składzie.
                </p>
            @else
                @if($participantCount > 12)
                    <p class="text-accent/70 text-xs mb-2">Duża lista — przewiń, aby zobaczyć wszystkich.</p>
                @endif
                <div class="max-h-36 sm:max-h-44 overflow-y-auto overflow-x-hidden pr-1 -mr-1">
                    <div class="flex flex-wrap gap-2">
                        @foreach($participants as $participant)
                            <div class="flex items-center gap-1 bg-bg text-text-secondary pl-3 pr-1 py-1.5 rounded-lg  text-sm">
                                <span>
                                    {{ $participant['name'] }}
                                    @if($participant['kind'] === 'guest')
                                        <span class="text-xs text-accent ml-1">gość</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @if(! $canManageParticipants)
            <div class="mb-8 card">
                @if(!empty($join['alreadyParticipant']))
                    <p class="text-text-secondary text-sm">Jesteś w składzie tego turnieju.</p>
                @elseif(!empty($join['alreadyPending']))
                    <p class="text-text-secondary text-sm">Zgłoszenie wysłane. Czekasz na decyzję organizatora.</p>
                @elseif(!empty($join['canApply']))
                    @auth
                        <form action="{{ route('tournaments.join-requests.store', $tournament->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary">Zgłoś się</button>
                        </form>
                    @else
                        <a href="{{ route('pages.loginPanel') }}" class="btn btn-primary">Zaloguj się, aby się zgłosić</a>
                    @endauth
                @elseif(!empty($join['reason']))
                    <p class="text-text-secondary text-sm">{{ $join['reason'] }}</p>
                @endif
            </div>
        @endif

        @if($canManageParticipants)
            {{-- Strefa 2: Dodaj uczestników — live WS zawsze (nie tylko przy QR) --}}
            <div
                class="mb-8 card overflow-hidden"
                x-data="tournamentJoinRequestsLive(@js([
                    'channel' => 'tournament.'.$tournament->id,
                    'snapshotUrl' => route('tournaments.join-requests-live', $tournament->id),
                    'csrfToken' => csrf_token(),
                    'inviteUrl' => route('tournaments.invitations.send', $tournament->id),
                    'joinSelfUrl' => route('tournaments.invitations.join-self', $tournament->id),
                    'currentUserId' => auth()->id(),
                    'createGuestUrl' => route('tournaments.participants.guests.create', $tournament->id),
                    'addGuestUrl' => route('tournaments.participants.guests.add', $tournament->id),
                    'invitationPipeline' => $invitationPipelineLive ?? [],
                    'relatedGuests' => ($relatedGuests ?? collect())->values()->all(),
                    'initialRequests' => $pendingJoinRequestsLive ?? [],
                    'initialParticipants' => $participantsLive ?? [],
                    'participantCount' => $participantCount,
                    'minPlayers' => $minPlayers,
                    'canManage' => true,
                    'reverb' => \App\Support\Broadcasting\ReverbClientConfig::forWeb(),
                ]))"
                x-init="init()"
            >
                <div class="border-b border-border px-6 pt-6 pb-4">
                    <h2 class="text-xl font-semibold text-accent mb-4">Dodaj uczestników</h2>

                    {{-- QR zgłoszenia --}}
                    @if(!empty($joinCode) && !empty($joinUrl))
                        <div class="mb-6 p-4 rounded-lg border border-border bg-bg/40">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <h3 class="text-accent font-semibold mb-0">Dołącz przez QR</h3>
                                <span
                                    class="px-2 py-0.5 rounded text-xs font-semibold bg-accent/25 text-accent"
                                    x-text="connectionLabel()"
                                >Łączenie…</span>
                            </div>
                            <p class="text-text-secondary text-sm mb-4">
                                Zawodnik skanuje kod → zgłasza się w aplikacji. Zgłoszenia pojawiają się poniżej na żywo — zatwierdzasz Dołącz / Odrzuć.
                            </p>
                            <div class="flex flex-wrap items-start gap-6">
                                <div class="bg-white p-3 rounded-lg inline-block">
                                    <img
                                        src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&amp;data={{ urlencode($joinUrl) }}"
                                        width="180"
                                        height="180"
                                        alt="QR dołączania do turnieju"
                                    >
                                </div>
                                <div class="flex-1 min-w-[180px]">
                                    <p class="text-text-secondary text-sm mb-1">Kod</p>
                                    <p class="font-mono text-2xl tracking-widest text-accent mb-3">{{ $joinCode }}</p>
                                    <p class="text-text-secondary text-xs break-all mb-4">{{ $joinUrl }}</p>
                                    <div class="flex flex-wrap gap-2">
                                        <form action="{{ route('tournaments.join-code.regenerate', $tournament->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn-mini">Nowy kod</button>
                                        </form>
                                        <form action="{{ route('tournaments.join-code.toggle', $tournament->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="enabled" value="{{ !empty($joinCodeEnabled) ? 0 : 1 }}">
                                            <button type="submit" class="btn-mini">
                                                {{ !empty($joinCodeEnabled) ? 'Wyłącz zgłoszenia' : 'Włącz zgłoszenia' }}
                                            </button>
                                        </form>
                                    </div>
                                    @unless(!empty($joinCodeEnabled))
                                        <p class="text-danger text-sm mt-2">Przyjmowanie zgłoszeń jest wyłączone.</p>
                                    @endunless
                                </div>
                            </div>

                            <div class="mt-5">
                                <h4 class="text-accent font-semibold mb-2">
                                    Zgłoszenia
                                    <span
                                        class="text-sm font-normal text-text-secondary"
                                        x-show="requests.length > 0"
                                        x-text="'(' + requests.length + ')'"
                                    ></span>
                                </h4>
                                <p class="text-text-secondary text-sm" x-show="requests.length === 0">
                                    Brak oczekujących zgłoszeń.
                                </p>
                                <div
                                    class="overflow-x-auto rounded-lg border border-border"
                                    x-show="requests.length > 0"
                                    x-cloak
                                >
                                    <table class="w-full text-text-secondary text-sm">
                                        <thead class="bg-bg">
                                            <tr class="text-accent">
                                                <th class="text-left py-2 px-3">Zawodnik</th>
                                                <th class="text-left py-2 px-3">Zgłoszono</th>
                                                <th class="text-left py-2 px-3">Akcja</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="req in requests" :key="req.id">
                                                <tr class="border-t border-border/50">
                                                    <td class="py-2 px-3" x-text="req.playerName"></td>
                                                    <td class="py-2 px-3" x-text="req.createdAt"></td>
                                                    <td class="py-2 px-3">
                                                        <div class="flex flex-wrap gap-2">
                                                            <button
                                                                type="button"
                                                                class="btn-mini"
                                                                :disabled="busyId === req.id"
                                                                @click="resolveRequest(req, 'approve')"
                                                            >Dołącz</button>
                                                            <button
                                                                type="button"
                                                                class="btn-mini"
                                                                :disabled="busyId === req.id"
                                                                @click="resolveRequest(req, 'reject')"
                                                            >Odrzuć</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Wyszukiwarka — zawsze widoczna (niezależnie od zakładek) --}}
                    <div
                        class="mb-2"
                        x-data="tournamentUserSearch(@js([
                            'searchUrl' => route('tournaments.invitations.search', $tournament->id),
                            'csrfToken' => csrf_token(),
                        ]))"
                    >
                        <h3 class="text-accent font-semibold mb-2">Wyszukaj użytkownika</h3>
                        <div
                            class="mb-4"
                            x-show="!$store.tournamentStartLive.isCurrentUserParticipant()"
                            x-cloak
                        >
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="$store.tournamentStartLive.inviteBusyKey === 'join-self'"
                                @click="$store.tournamentStartLive.joinSelf()"
                            >Dodaj mnie</button>
                            <p class="text-text-secondary text-sm mt-2">
                                Wejdziesz od razu na listę uczestników, bez akceptacji w aplikacji.
                            </p>
                        </div>
                        <p class="text-text-secondary text-sm mb-3">Wpisz imię lub fragment nazwy gracza i wyślij zaproszenie.</p>
                        <form @submit.prevent="search()" class="flex flex-wrap items-center gap-4">
                            <input
                                type="text"
                                x-model="query"
                                placeholder="Min. 2 znaki..."
                                class="input-field flex-1 min-w-[200px]"
                                autocomplete="off"
                            >
                            <button type="submit" class="btn btn-primary" :disabled="loading">
                                <span x-text="loading ? 'Szukam…' : 'Szukaj'"></span>
                            </button>
                        </form>

                        <p class="text-text-secondary mt-3 text-sm" x-show="searched && results.length === 0" x-cloak>
                            Brak wyników.
                        </p>
                        <div class="flex flex-wrap gap-2 mt-3" x-show="results.length > 0" x-cloak>
                            <template x-for="user in results" :key="user.id">
                                <div class="flex items-center gap-2 bg-bg rounded-lg px-3 py-2">
                                    <span class="text-text-secondary text-sm" x-text="user.name"></span>
                                    <button
                                        type="button"
                                        class="btn-mini"
                                        :disabled="$store.tournamentStartLive.inviteBusyKey === ('search-' + user.id)"
                                        @click="invite(user)"
                                    >Zaproś</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Goście bez konta — zawsze widoczne --}}
                    <div class="mt-6 pt-6 border-t border-border">
                        <h3 class="text-accent font-semibold mb-2">Dodaj gościa (bez konta)</h3>
                        <p class="text-text-secondary text-sm mb-3">
                            Gracz niezarejestrowany w aplikacji — trafi od razu na listę uczestników turnieju.
                        </p>
                        <form
                            @submit.prevent="$store.tournamentStartLive.createGuest()"
                            class="flex flex-wrap items-center gap-4"
                        >
                            <input type="text"
                                   x-model="$store.tournamentStartLive.guestName"
                                   placeholder="Imię gościa..."
                                   maxlength="20"
                                   class="input-field flex-1 min-w-[200px]"
                                   required>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="$store.tournamentStartLive.guestBusy"
                            >
                                <span x-text="$store.tournamentStartLive.guestBusy ? 'Dodaję…' : 'Dodaj gościa'"></span>
                            </button>
                        </form>
                    </div>

                    {{-- Oczekujące zaproszenia --}}
                    <div class="mt-6" x-show="$store.tournamentStartLive.invitationPipeline.length > 0" x-cloak>
                        <h3 class="text-accent font-semibold mb-2">Zaproszenia w toku</h3>
                        <div class="overflow-x-auto rounded-lg border border-border">
                            <table class="w-full text-text-secondary text-sm">
                                <thead class="bg-bg">
                                    <tr class="text-accent">
                                        <th class="text-left py-2 px-3">Zawodnik</th>
                                        <th class="text-left py-2 px-3">Status</th>
                                        <th class="text-left py-2 px-3">Akcja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template
                                        x-for="inv in $store.tournamentStartLive.invitationPipeline"
                                        :key="inv.id"
                                    >
                                        <tr class="border-t border-border/50">
                                            <td class="py-2 px-3" x-text="inv.name"></td>
                                            <td class="py-2 px-3" x-text="inv.statusLabel"></td>
                                            <td class="py-2 px-3">
                                                <button
                                                    type="button"
                                                    class="btn-mini"
                                                    x-show="inv.isPending"
                                                    :disabled="$store.tournamentStartLive.inviteBusyKey === ('cancel-' + inv.id)"
                                                    @click="$store.tournamentStartLive.cancelInvitation(inv)"
                                                >Anuluj</button>
                                                <button
                                                    type="button"
                                                    class="btn-mini"
                                                    x-show="inv.canReinvite"
                                                    :disabled="$store.tournamentStartLive.inviteBusyKey === ('reinvite-' + inv.id)"
                                                    @click="$store.tournamentStartLive.reinvite(inv)"
                                                >Zaproś ponownie</button>
                                                <span
                                                    class="text-accent/60"
                                                    x-show="!inv.isPending && !inv.canReinvite"
                                                >—</span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @if($tournament->season)
                <div
                    x-data="{ activeTab: '{{ $addTab }}' }"
                >
                    <div class="border-b border-border px-6">
                        <div class="flex gap-1">
                            <button
                                type="button"
                                x-on:click="activeTab = 'registered'"
                                x-bind:class="activeTab === 'registered'
                                    ? 'bg-accent text-on-accent'
                                    : 'bg-bg text-text-secondary hover:bg-bg/80'"
                                class="px-5 py-2 rounded-t-lg font-semibold text-sm transition"
                            >
                                Stały skład
                            </button>
                            <button
                                type="button"
                                x-on:click="activeTab = 'guests'"
                                x-bind:class="activeTab === 'guests'
                                    ? 'bg-accent text-on-accent'
                                    : 'bg-bg text-text-secondary hover:bg-bg/80'"
                                class="px-5 py-2 rounded-t-lg font-semibold text-sm transition"
                            >
                                Goście organizacji
                            </button>
                        </div>
                    </div>

                    <div class="p-6">
                        {{-- Stały skład --}}
                        <div x-show="activeTab === 'registered'">
                            <div x-data="{ selectedRegulars: [] }">
                                <h3 class="text-accent font-semibold mb-2">Stały skład organizacji</h3>
                                <p class="text-text-secondary text-sm mb-3">Zaznacz bywalców i wyślij masowe zaproszenia.</p>

                                @if($regulars->isEmpty())
                                    <p class="text-text-secondary text-sm">
                                        Brak powiązanych użytkowników.
                                        <a href="{{ route('organizations.relatedUsers', $tournament->season->organization->id) }}" class="text-accent underline">Organizacja</a>
                                    </p>
                                @else
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        @foreach($regulars as $regular)
                                            @if($regular['canInvite'])
                                                <div
                                                    x-on:click="selectedRegulars.includes({{ $regular['userId'] }})
                                                        ? selectedRegulars = selectedRegulars.filter(id => id !== {{ $regular['userId'] }})
                                                        : selectedRegulars.push({{ $regular['userId'] }})"
                                                    x-bind:class="selectedRegulars.includes({{ $regular['userId'] }})
                                                        ? 'bg-accent text-on-accent'
                                                        : 'bg-bg text-text-secondary'"
                                                    class="cursor-pointer px-3 py-2 rounded-lg text-sm transition  select-none"
                                                >
                                                    {{ $regular['name'] }}
                                                </div>
                                            @else
                                                <div class="px-3 py-2 rounded-lg text-sm bg-bg/50 text-text-secondary/60 border border-accent/20">
                                                    {{ $regular['name'] }}
                                                    <span class="text-xs text-accent block">{{ $regular['invitationStatus']?->label() }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>

                                    <form action="{{ route('tournaments.invitations.bulk', $tournament->id) }}" method="POST">
                                        @csrf
                                        <template x-for="userId in selectedRegulars" x-bind:key="userId">
                                            <input type="hidden" name="user_ids[]" x-bind:value="userId">
                                        </template>
                                        <button type="submit" class="btn btn-primary" x-bind:disabled="selectedRegulars.length === 0">
                                            Wyślij zaproszenie (<span x-text="selectedRegulars.length"></span>)
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        {{-- Goście --}}
                        <div x-show="activeTab === 'guests'" style="display: none;">
                            <h3 class="text-accent font-semibold mb-2">Powiązani goście</h3>
                            <p class="text-text-secondary text-sm mb-4">Dodaj gości z puli organizacji do tego turnieju.</p>

                            <p
                                class="text-text-secondary text-sm"
                                x-show="$store.tournamentStartLive.relatedGuests.length === 0"
                            >
                                Brak powiązanych gości.
                                <a href="{{ route('organizations.guests', $tournament->season->organization->id) }}" class="text-accent underline">Organizacja</a>
                            </p>
                            <div
                                class="flex flex-wrap gap-3"
                                x-show="$store.tournamentStartLive.relatedGuests.length > 0"
                                x-cloak
                            >
                                <template
                                    x-for="guest in $store.tournamentStartLive.relatedGuests"
                                    :key="guest.playerId"
                                >
                                    <div class="flex flex-col items-center bg-bg rounded-lg p-4 min-w-[110px]">
                                        <span class="text-text-secondary text-sm text-center mb-2" x-text="guest.name"></span>
                                        <span
                                            class="text-xs text-accent font-semibold"
                                            x-show="guest.inTournament"
                                        >W turnieju</span>
                                        <button
                                            type="button"
                                            class="btn-mini"
                                            x-show="!guest.inTournament"
                                            :disabled="$store.tournamentStartLive.busyKey === ('related-' + guest.playerId)"
                                            @click="$store.tournamentStartLive.addRelatedGuest(guest)"
                                        >Dodaj</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        @endif
