# twentySix — stan implementacji vs MVP

Mapa zgodności kodu z [`docs/product.md`](docs/product.md).  
**Legenda:** ✅ gotowe · ⚠️ częściowo · ❌ brak

Ostatnia aktualizacja: październik 2026. **MVP v1** (tag `v1.0.0-mvp`, lipiec 2026) jest spełnione. Poniżej: kryteria MVP oraz to, co doszło w kodzie po tagu.

---

## Podsumowanie

| Obszar | Stan |
|--------|------|
| **Kryteria MVP v1** | Spełnione (web, API, mobile) |
| **Po tagu** | Liga, SE/DE do 128, drabinka pocieszenia, `/referee`, kariera web i mobile, live grup i drabinki, tryby quick/trening poza X01 |
| **Mobile** | [`../twentysix-mobile/IMPLEMENTED_FEATURES.md`](../twentysix-mobile/IMPLEMENTED_FEATURES.md) |

Szczegóły wymagań: [`docs/product.md`](docs/product.md). Otwarte tematy: [`docs/NEXT_STEPS.md`](docs/NEXT_STEPS.md).

**Weryfikacja:** scenariusze manualne w [`docs/README.md`](docs/README.md). Suite: `php artisan test` (liczba testów rośnie razem z kodem — nie trzymamy tu zamrożonego wyniku z lipca).

---

## Web

| Wymaganie MVP | Status | Pliki / uwagi |
|---------------|--------|---------------|
| Twórca organizacji = organizator | ✅ | `OrganizationRepository`, `OrganizationController`, `OrganizationPolicy` |
| Współadmin per organizacja (pełne prawa) | ✅ | `/organizations/{id}/admins/*`, `OrganizationPolicy` |
| Turniej: goście (nazwa) | ✅ | `SeasonController`, `PlayerRepository` |
| Turniej: zaproszenia (wyszukiwarka + akceptacja) | ✅ | Strona startu: wysyłka, masowy invite ze składu (`relatedUsers`); mobile: accept/reject/withdraw |
| Start turnieju: liczba grup | ✅ | Dowolna liczba od 2, min. 3 osoby w grupie — `TournamentStartRules` |
| Start: walidowany awans z grupy | ✅ | Kreator + `TournamentStartValidator` |
| Start: jeden kod tabletu 8 znaków + QR | ✅ | `LoginCodeService::generateForTournament`, `/tablet-login/{code}` |
| Start: tylko zaakceptowani + goście | ✅ | `getTournamentStartPool`, walidacja przy `run` |
| Publiczny podgląd organizacji/turniejów | ✅ | Gość bez logowania — [`scenariusze_manualne_web_gosc_krok3.md`](docs/scenariusze_manualne_web_gosc_krok3.md) |
| Korekta wyniku / walkower na webie | ✅ | `games/show` — formularz admina sezonu, `GameResultCorrectionService` |
| Live podgląd meczu (WebSocket) | ✅ | `games/{type}/{id}/live`, `gameLiveViewer.js`, Reverb `game.state` |
| Live turnieju na webie (grupy, drabinka, zgłoszenia QR) | ✅ | `tournamentGroupsLive.js`, `tournamentPlayoffLive.js`, `tournamentJoinRequestsLive.js` — Reverb, bez odświeżania strony |
| Live sesji FFA | ✅ | `/quick-game/lobby/{lobbyId}/live`, `ffaLiveViewer.js` |
| Znajomi na webie | ✅ | Profil gracza: invite → accept; panel boczny (przychodzące / znajomi / oczekujący); `FriendInvitationController` |
| Presety formatu gry w organizacji | ✅ | `organizations.match_format_presets`, edycja organizacji → domyślne w kreatorze startu turnieju |

---

## API

| Wymaganie MVP | Status | Pliki / uwagi |
|---------------|--------|---------------|
| Min. 2 graczy quick game | ✅ | `QuickGameLobbyService::start` |
| Min. 4 graczy turniej | ✅ | `TournamentStartValidator`, `TournamentController` |
| Pula: zaakceptowani + goście | ✅ | `tournament_invitations`, `PlayerService::getTournamentStartPool` |
| Podział grup (od grupy 1, równe wielkości) | ✅ | `TournamentGroupDistribution` |
| Round-robin w grupie | ✅ | `generateGamesForGroup` |
| Tie-breakery grupowe | ✅ | `GroupStandingService` |
| Auto start playoff | ✅ | `GameService::handlePlayoffStart` |
| Awans z grupy (etap drabinki) | ✅ | `playoff_bracket_size`, `group_advances`, `PlayoffService` |
| Bracket (potęga 2, max 128) | ✅ | `TournamentStartRules::MAX_BRACKET_SIZE`, `PlayoffBracketFactory` |
| Playoff R1: rozstawienie z miejsc w grupach | ✅ | `PlayoffFirstRoundSeeding` |
| Statusy meczu + lock tabletu | ✅ | `GameLockService`, `POST /api/game/inProgress`, mobile `lockTournamentGame` |
| Kody tabletów | ✅ | `POST /api/login`, `LoginCodeService` |
| Znajomi (invite/accept/reject) | ✅ | `/api/friends/*` |
| Zaproszenia turniejowe API | ✅ | `/api/tournaments/invitations/*` |
| Lobby quick game | ✅ | `/api/quick-game/lobby/*`, FFA `/ffa/*` |
| Lobby: tylko znajomi | ✅ | `QuickGameLobbyService::invite`, testy MVP |
| Quick game: `one_device` / `each_own` | ✅ | Unified FFA N=2..8 + WS |
| Quick game FFA do 8 | ✅ | `QuickGameFfaScoringService`, cap 8 |
| Scoring API turniej | ✅ | `GameScoringService`, group/playoff |
| Wspólny silnik wizyt | ✅ | `VisitRecorder`, `ScoringStateContract` |
| Legacy H2H quick scoring | ❌ wycofane | API `/quick-games/{id}/scoring/*` usunięte; quick online tylko FFA z lobby |
| Legacy `quick-game/create|active|inProgress` | ❌ wycofane | Usunięte (lipiec 2026); wynik FFA + `POST /api/quick-game/update` (achievementy) |
| Achievementy quick game online | ✅ | `POST /api/quick-game/update` (tylko `gameId` + achievements) |
| Finalizacja turnieju po scoring API | ✅ | `GameService::finalizeTournamentGameFromScoring` po `closeLeg` (tabele, playoff, statystyki) |
| Achievementy turniejowe (180, 170+, HF, QF) | ✅ | Wizyty i statystyki lega; `POST /api/game/update` ich nie zapisuje |
| Achievementy turniejowe | ✅ | `AchievementsService` |
| Auto point scheme | ✅ | `PointSchemeService::findByPlayersAmount` |
| WebSocket (Reverb) | ✅ | `GameScoringStateUpdated`, `QuickGameLobbyUpdated`, `channels.php` |
| FFA presence + walkower 2P | ✅ | `POST .../ffa/presence`, `GET .../active-match`, `QuickGameFfaPresenceService` |
| Znajomi web (invite flow) | ✅ | `PlayerController`, `FriendInvitationController`, test `PlayerFriendInvitationWebTest` |
| Push zaproszeń (Expo) | ✅ | `user_push_tokens`, `PUT/DELETE /api/push-tokens`, `SendInvitationPushJob`, hooki friends/tournament/lobby |

---

## Mobile (skrót — szczegóły w repo mobile)

| Wymaganie MVP | Status |
|---------------|--------|
| Tablet: kod + lista meczów + H2H | ✅ |
| Tablet: grupy → mecze; playoff płaska lista | ✅ | `ActiveGameDTO.roundLabel`, mobile `GameList.jsx` |
| Lock meczu `w trakcie` | ✅ | `lockTournamentGame`, `GameLockService` |
| Quick game: tryby urządzeń (online FFA) | ✅ |
| Quick game FFA 2–8 + rotacja legów | ✅ |
| FFA presence, walkower, powrót do meczu | ✅ | `useGameScoring`, `Home.jsx` + `GET /active-match` |
| Trening mobile | ✅ | Bez konta bez zapisu; zalogowany slot JA idzie na konto — `TrainingMatchSetup.jsx` |
| Znajomi: invite + accept (mobile) | ✅ |
| Marka twentySix w UI | ✅ |

---

## Po tagu `v1.0.0-mvp` (w kodzie)

| Obszar | Status | Pliki / uwagi |
|--------|--------|----------------|
| Liga (piramida) | ✅ | `app/Domain/League`, `LeagueSeasonService`, sezon ligowy, baraże, awans/spadek |
| Mecz ligowy | ✅ | Lobby i scoring API `LeagueGameScoringController`; gość bez konta — wynik wpisuje admin na webie |
| Single / double elimination | ✅ | `DoubleEliminationBracketFactory`, `DoubleEliminationPlacement`; wybór przy starcie |
| Drabinka pocieszenia | ✅ | Opcja przy `groups_playoff`; osobna drabinka SE |
| Sędziowanie w przeglądarce | ✅ | `routes/web.php` prefix `referee` — ten sam kod tabletu |
| Kariera gracza | ✅ | Web `players/partials/career-dashboard` i mobile `ProfileCareerDashboard` — okna, źródła, średnia X01, duble, wykresy. Zestawy metryk pozostałych trybów na profilu: [`docs/NEXT_STEPS.md`](docs/NEXT_STEPS.md) |
| Trening slotu JA | ✅ | Zapis na konto zalogowanego; lokalne imię zostaje lokalne |
| Cricket, Bob's 27, ATC, Catch 40, Cricket 60 | ✅ | Trening + quick FFA. Brak w turnieju |
| Punktacja sezonu od N | ✅ | Pasma 4–128, `PointSchemeDomain` |
| Ogranicznik lotek X01 | ✅ | `dartLimit` / `lossThreshold`, bull-off |
| Anulowanie meczu w trakcie | ✅ | Turniej i liga — powrót do `oczekujący` |
| Overlay OBS (H2H) | ✅ | Podgląd meczu pod transmisję |
| Ukrycie bytu na 90 dni | ✅ | Organizacja, sezon, turniej, liga, sezon ligowy — bez twardego kasowania |
| Kolejność meczów w grupie i sędzia z grupy | ✅ | Metoda koła; lista sędziów pod tabelą |

---

## MVP v1 — zamknięte (lipiec 2026)

Wszystkie punkty poniżej ✅. Kolejne prace: [`docs/NEXT_STEPS.md`](docs/NEXT_STEPS.md).

1. ~~Turniej — logika (grupy, awans, bracket, min. 4 graczy)~~
2. ~~Zaproszenia turniejowe — API + web + mobile~~
3. ~~Web — korekta / walkower, live meczu~~
4. ~~Tablet mobile — lock, playoff, scoring API + WS~~
5. ~~Quick game FFA 2–8, oba tryby urządzeń~~
6. ~~Trening mobile (offline, bez zapisu)~~
7. ~~Web gość, znajomi web, testy auto~~
8. ~~Release RC + tag `v1.0.0-mvp`~~

---

## Testy (backend)

| Obszar | Pliki testów |
|--------|----------------|
| Walidacja startu | `tests/Unit/Tournament/TournamentStartValidatorTest.php` |
| Podział do grup | `tests/Unit/Tournament/TournamentGroupDistributionTest.php` |
| Drabinka playoff | `tests/Unit/Tournament/PlayoffBracketFactoryTest.php` |
| Rozstawienie R1 | `tests/Unit/Tournament/PlayoffFirstRoundSeedingTest.php` |
| Awans / playoff | `tests/Feature/PlayoffAdvanceTest.php` |
| Flow E2E (start → grupy → playoff) | `tests/Feature/TournamentFlowTest.php` |
| Scoring API → finalizacja turnieju | `tests/Feature/TournamentGameScoringFinalizeTest.php` |
| VisitRecorder (unit) | `tests/Unit/GameScoring/VisitRecorderTest.php` |
| Quick game FFA finalize | `tests/Feature/QuickGameFfaScoringApiTest.php` |
| Lobby MVP | `tests/Feature/QuickGameLobbyMvpTest.php` |
| FFA presence / walkover | `tests/Feature/QuickGameFfaPresenceApiTest.php` |
| Znajomi web (invite → accept) | `tests/Feature/PlayerFriendInvitationWebTest.php` |
| Achievementy po FFA | `tests/Feature/QuickGameApiTest.php` |

Pełna suite: `php artisan test`. Wynik z lipca 2026 (172 passed, 14 skipped) jest historyczny i nie opisuje dzisiejszego zestawu.

---

## Powiązane dokumenty

- [`docs/product.md`](docs/product.md) — wizja i MVP
- [`docs/NEXT_STEPS.md`](docs/NEXT_STEPS.md) — aktywne zadania
- [`docs/README.md`](docs/README.md) — indeks dokumentacji
- [`LOGIKA_BIZNESOWA.md`](LOGIKA_BIZNESOWA.md) — przepływy
- [`README.md`](README.md) — uruchomienie dev / deploy
- Mobile: [`../twentysix-mobile/IMPLEMENTED_FEATURES.md`](../twentysix-mobile/IMPLEMENTED_FEATURES.md)
