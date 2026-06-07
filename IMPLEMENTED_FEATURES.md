# twentySix — stan implementacji vs MVP

Mapa zgodności kodu z [`docs/product.md`](docs/product.md).  
**Legenda:** ✅ gotowe · ⚠️ częściowo · ❌ brak

Ostatnia aktualizacja: czerwiec 2026.

---

## Podsumowanie

| Obszar | Postęp | Najważniejsze luki |
|--------|--------|-------------------|
| **Web** | ~80% | Korekta wyniku na webie |
| **API** | ~85% | Lock tabletu (mobile) |
| **Mobile** | ~50% | Zob. [`../twentysix-mobile/IMPLEMENTED_FEATURES.md`](../twentysix-mobile/IMPLEMENTED_FEATURES.md) |

Szczegóły znanych rozbieżności: sekcja „Uwagi dla implementacji” w `product.md`.

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
| Publiczny podgląd lig/turniejów | ⚠️ | Widoki istnieją; weryfikacja gościa bez logowania — do sprawdzenia |
| Korekta wyniku / walkower na webie | ❌ | `matches/show` — tylko podgląd; edycja tylko przez API |
| Live podgląd meczu (WebSocket) | ✅ | `matches/{type}/{id}/live`, `match-live.js`, Reverb `match.state` |
| Live WebSocket na webie (turniej) | ❌ | Brak widoku live całego turnieju z WS |
| Znajomi na webie | ⚠️ | UI częściowo; product: poza MVP na webie |

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
| Awans z grupy (wybór admina) | ✅ | `advance_per_group` na turnieju, `PlayoffService` |
| Bracket `groups × awans` (potęga 2) | ✅ | `PlayoffBracketFactory::create` (2–32) |
| Playoff R1: bez par z tej samej grupy | ✅ | `PlayoffFirstRoundPairing` |
| Statusy meczu + lock tabletu | ⚠️ | `GameStatus`, `inProgress`/`active` — mobile nie zawsze woła lock |
| Kody tabletów | ✅ | `POST /api/login`, `LoginCodeService` |
| Znajomi (invite/accept/reject) | ✅ | `/api/friends/*` |
| Zaproszenia turniejowe API | ✅ | `/api/tournaments/invitations/*` |
| Lobby quick game | ✅ | `/api/quick-game/lobby/*` |
| Lobby: tylko znajomi | ❌ | Brak walidacji friends-only + goście w API |
| Quick game: `one_device` / `each_own` | ✅ | `scoring_mode` w lobby |
| Quick game FFA do 8 | ⚠️ | Cap 6 w lobby; wynik multi-player częściowy |
| Scoring API turniej + quick | ✅ | `MatchScoringService`, osobne kontrolery |
| Achievementy turniejowe | ✅ | `AchievementsService` |
| Auto point scheme | ✅ | `PointSchemeService::findByPlayersAmount` |
| WebSocket (Reverb) | ✅ | `MatchScoringStateUpdated`, `QuickGameLobbyUpdated`, `channels.php` |

---

## Mobile (skrót — szczegóły w repo mobile)

| Wymaganie MVP | Status |
|---------------|--------|
| Tablet: kod + lista meczów + H2H | ⚠️ |
| Tablet: grupy → mecze; playoff płaska lista | ⚠️ |
| Lock meczu `w trakcie` | ❌ |
| Quick game: tryby urządzeń (2P online) | ✅ |
| Quick game FFA 3–8 + rotacja legów | ⚠️ |
| Znajomi: invite + accept (mobile) | ⚠️ |
| Akceptacja zaproszenia turniejowego | ❌ |
| Akceptacja lobby | ✅ |
| Offline / solo ćwiczenia | ❌ |
| Marka twentySix w UI | ⚠️ |

---

## Priorytetowe zadania do MVP (sugerowana kolejność)

1. ~~**Turniej — logika:** podział grup, awans z grupy, bracket dynamiczny, playoff R1 bez par z grupy, min. 4 graczy.~~ ✅ *(czerwiec 2026)*
2. ~~**Zaproszenia turniejowe:** API + web (wysyłka) + mobile (akceptacja).~~ ✅ *(czerwiec 2026)*
3. **Web:** edycja wyniku / walkower; ~~live podgląd meczu~~ ✅ *(czerwiec 2026)*.
4. **Tablet mobile:** lock meczu, playoff UI, scoring API + WS.
5. **Quick game:** FFA do 8, rotacja openera lega, friends-only, multi-device 3+.
6. **Offline / solo** na mobile.

---

## Testy logiki turniejowej (backend)

| Obszar | Pliki testów |
|--------|----------------|
| Walidacja startu | `tests/Unit/Tournament/TournamentStartValidatorTest.php` |
| Podział do grup | `tests/Unit/Tournament/TournamentGroupDistributionTest.php` |
| Drabinka playoff | `tests/Unit/Tournament/PlayoffBracketFactoryTest.php` |
| Parowanie R1 | `tests/Unit/Tournament/PlayoffFirstRoundPairingTest.php` |
| Awans / playoff | `tests/Feature/PlayoffAdvanceTest.php` |
| Flow E2E (start → grupy → playoff) | `tests/Feature/TournamentFlowTest.php` |
| Start HTTP | `tests/Feature/TournamentControllerTest.php` |

---

## Powiązane dokumenty

- [`docs/product.md`](docs/product.md) — wizja i MVP
- [`LOGIKA_BIZNESOWA.md`](LOGIKA_BIZNESOWA.md) — przepływy
- Mobile: `../twentysix-mobile/IMPLEMENTED_FEATURES.md`
