# twentySix — stan implementacji vs MVP

Mapa zgodności kodu z [`docs/product.md`](docs/product.md).  
**Legenda:** ✅ gotowe · ⚠️ częściowo · ❌ brak

Ostatnia aktualizacja: lipiec 2026 (krok 5 — domknięcie docs).

---

## Podsumowanie

| Obszar | Postęp | Najważniejsze luki |
|--------|--------|-------------------|
| **Web** | ~95% | Live całego turnieju (WS) — poza MVP |
| **API** | ~90% | — |
| **Mobile** | ~90% | Zob. [`../twentysix-mobile/IMPLEMENTED_FEATURES.md`](../twentysix-mobile/IMPLEMENTED_FEATURES.md) |

Szczegóły znanych rozbieżności: sekcja „Uwagi dla implementacji” w `product.md`.

**Weryfikacja MVP (manual + auto):** kroki 2–4 planu [`docs/plan_mvp_domkniecie.md`](docs/plan_mvp_domkniecie.md) ✅ · `php artisan test` — 172 passed, 14 skipped (lipiec 2026).

---

## Web

| Wymaganie MVP | Status | Pliki / uwagi |
|---------------|--------|---------------|
| Twórca ligi = organizator | ✅ | `LeagueRepository`, `LeagueController`, `LeaguePolicy` |
| Współadmin per liga (pełne prawa) | ✅ | `/leagues/{id}/admins/*`, `LeaguePolicy` |
| Turniej: goście (nazwa) | ✅ | `SeasonController`, `PlayerRepository` |
| Turniej: zaproszenia (wyszukiwarka + akceptacja) | ⚠️ | Strona startu: wysyłka, masowy invite ze składu; mobile: akceptacja/wycofanie |
| Start turnieju: liczba grup | ✅ | `start.blade.php` — potęgi 2 (2…64), `TournamentStartRules` |
| Start: walidowany awans z grupy | ✅ | Kreator + `TournamentStartValidator` |
| Start: liczba kodów tabletów (≠ liczba grup) | ✅ | `tabletsCount` w kreatorze, `LoginCodeService` |
| Start: tylko zaakceptowani + goście | ✅ | `getTournamentStartPool`, walidacja przy `run` |
| Publiczny podgląd lig/turniejów | ✅ | Gość bez logowania — krok 3: [`scenariusze_manualne_web_gosc_krok3.md`](docs/scenariusze_manualne_web_gosc_krok3.md) |
| Korekta wyniku / walkower na webie | ✅ | `games/show` — formularz admina sezonu, `GameResultCorrectionService` |
| Live podgląd meczu (WebSocket) | ✅ | `games/{type}/{id}/live`, `game-live.js`, Reverb `game.state` |
| Live WebSocket na webie (turniej) | ❌ | Brak widoku live całego turnieju z WS |
| Znajomi na webie | ✅ | Profil gracza: invite → accept; panel boczny (przychodzące / znajomi / oczekujący); `FriendInvitationController` |

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
| Bracket `groups × awans` (potęga 2) | ✅ | `PlayoffBracketFactory::create` (2–32) |
| Playoff R1: bez par z tej samej grupy | ✅ | `PlayoffFirstRoundPairing` |
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
| Achievementy quick game online | ✅ | `POST /api/quick-game/update` (tylko `gameId` + achievements) |
| Finalizacja turnieju po scoring API | ✅ | `GameService::finalizeTournamentGameFromScoring` po `closeLeg` (tabele, playoff, statystyki) |
| Achievementy na zakończonym meczu | ✅ | `POST /api/game/update` — tylko achievementy gdy gra `FINISHED`; bulk finish odrzucony |
| Achievementy turniejowe | ✅ | `AchievementsService` |
| Auto point scheme | ✅ | `PointSchemeService::findByPlayersAmount` |
| WebSocket (Reverb) | ✅ | `GameScoringStateUpdated`, `QuickGameLobbyUpdated`, `channels.php` |
| FFA presence + walkower 2P | ✅ | `POST .../ffa/presence`, `GET .../active-match`, `QuickGameFfaPresenceService` |
| Znajomi web (invite flow) | ✅ | `PlayerController`, `FriendInvitationController`, test `PlayerFriendInvitationWebTest` |

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
| Trening mobile (bez zapisu) | ✅ | `TrainingMatchSetup.jsx` |
| Znajomi: invite + accept (mobile) | ✅ |
| Marka twentySix w UI | ✅ |

---

## Priorytetowe zadania do MVP (sugerowana kolejność)

1. ~~**Turniej — logika:** podział grup, awans z grupy, bracket dynamiczny, playoff R1 bez par z grupy, min. 4 graczy.~~ ✅ *(czerwiec 2026)*
2. ~~**Zaproszenia turniejowe:** API + web (wysyłka) + mobile (akceptacja).~~ ✅ *(czerwiec 2026)*
3. ~~**Web:** edycja wyniku / walkower; live podgląd meczu~~ ✅ *(czerwiec 2026)*.
4. ~~**Tablet mobile:** lock meczu; playoff UI; scoring API + WS.~~ ✅ *(czerwiec 2026)*
5. ~~**Quick game:** FFA do 8, rotacja openera, friends-only, multi-device 3+.~~ ✅ *(czerwiec 2026)*
6. ~~**Trening mobile** (offline/local, bez zapisu).~~ ✅ *(czerwiec 2026)*
7. ~~**Web gość + znajomi web + testy auto** (kroki 3–4 planu MVP).~~ ✅ *(lipiec 2026)*

---

## Testy (backend)

| Obszar | Pliki testów |
|--------|----------------|
| Walidacja startu | `tests/Unit/Tournament/TournamentStartValidatorTest.php` |
| Podział do grup | `tests/Unit/Tournament/TournamentGroupDistributionTest.php` |
| Drabinka playoff | `tests/Unit/Tournament/PlayoffBracketFactoryTest.php` |
| Parowanie R1 | `tests/Unit/Tournament/PlayoffFirstRoundPairingTest.php` |
| Awans / playoff | `tests/Feature/PlayoffAdvanceTest.php` |
| Flow E2E (start → grupy → playoff) | `tests/Feature/TournamentFlowTest.php` |
| Scoring API → finalizacja turnieju | `tests/Feature/TournamentGameScoringFinalizeTest.php` |
| VisitRecorder (unit) | `tests/Unit/GameScoring/VisitRecorderTest.php` |
| Quick game FFA finalize | `tests/Feature/QuickGameFfaScoringApiTest.php` |
| Lobby MVP | `tests/Feature/QuickGameLobbyMvpTest.php` |
| FFA presence / walkover | `tests/Feature/QuickGameFfaPresenceApiTest.php` |
| Znajomi web (invite → accept) | `tests/Feature/PlayerFriendInvitationWebTest.php` |
| Achievementy po FFA | `tests/Feature/QuickGameApiTest.php` |

Pełna suite: `php artisan test` — **172 passed, 14 skipped** (lipiec 2026). Pominięte: widoki wymagające Vite manifest, legacy bulk quick-game POST.

---

## Powiązane dokumenty

- [`docs/product.md`](docs/product.md) — wizja i MVP
- [`LOGIKA_BIZNESOWA.md`](LOGIKA_BIZNESOWA.md) — przepływy
- [`docs/plan_mvp_domkniecie.md`](docs/plan_mvp_domkniecie.md) — plan domknięcia v1
- [`README.md`](README.md) — uruchomienie dev / deploy LAN
- Mobile: [`../twentysix-mobile/IMPLEMENTED_FEATURES.md`](../twentysix-mobile/IMPLEMENTED_FEATURES.md)
