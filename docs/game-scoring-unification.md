# Plan unifikacji scoringu rozgrywek twentySix

Dokument referencyjny na czas refaktoru warstwy scoringu (mobile + backend).
Ostatnia aktualizacja: 2026-06-06.

## Cele

1. **Jeden mental model**: mecz = legi + wizyty + sync; kontekst (trening / quick FFA / turniej) to adapter, nie osobna aplikacja.
2. **Jeden hook sync** na mobile: `useGameScoring`.
3. **Jeden mapper stanu** zamiast `applyGameScoringState` + `applyFfaScoringState`.
4. **Backend**: wspólny kształt odpowiedzi i wspólna logika wizyt — **bez** na siłę jednej tabeli `games` dla turnieju i lobby w pierwszej iteracji.

## Czego NIE scalamy (świadomie)

| Obszar | Dlaczego osobno |
|--------|-----------------|
| Trening | Brak API, brak zapisu — adapter `local` |
| Wejście turniej | Kod tabletu, lock/release, lista meczów |
| Wejście quick | Lobby, zaproszenia, `one_device` / `each_own` |
| Po meczu | Standingi/playoff vs statystyki quick vs nic |
| Format | `h2h` (2 graczy) vs `ffa` (2–8) — różna kolejka tur |
| Kanały WS | Public (`group-game.*`) vs private (`private-quick-game-lobby.*`) |
| Tabele DB (faza 1) | `game_visits` vs `quick_game_ffa_visits` — merge dopiero opcjonalnie |

## Docelowa architektura (mobile)

```
GameScoringScreen
    └── useGameScoring
            ├── normalizeScoringState
            ├── applyGameScoringState
            └── transport
                    ├── createTournamentTransport
                    ├── createFfaTransport
                    └── (trening: brak transportu)
```

## Kontrakt `GameScoringState` (mobile + API)

```js
{
  format: 'h2h' | 'ffa',
  revision: number,
  meta: {
    kind: 'training' | 'quick_ffa' | 'tournament_group' | 'tournament_playoff',
    legsToWin: number,
    startingScore: number,
    gameId?: number,
    lobbyId?: number,
    tournamentId?: number,
    quickGameId?: number,
    status: 'in_progress' | 'finished',
  },
  turn: {
    currentPlayerIndex: number,
    legOpenerIndex: number,
    legNumber: number,
  },
  currentLeg: { id?: number, legNumber: number, open: boolean } | null,
  players: Array<{
    playerId, name, remaining, legsWon,
    gameAverage, legAverage,
    legByLegScores?, legsAverages?, dartsPerLeg?,
  }>,
  visits: Array<{ playerId, score, dartsInVisit, bust, closedLeg, ... }>,
}
```

## Fazy realizacji

### Faza 0 — Przygotowanie

- Scenariusze manualne + testy: `TournamentGameScoringFinalizeTest`, `QuickGameFfaScoringApiTest`.
- Checklista regresji (patrz sekcja Testy).

### Faza 1 — Wspólny kontrakt stanu (mobile)

Pliki:

- `helpers/matchScoring/normalizeScoringState.js`
- `helpers/matchScoring/computeStateRevision.js`

- `fromTournamentState(raw)` — odpowiedź `GameScoringStateBuilder`
- `fromFfaState(raw)` — odpowiedź `QuickGameFfaStateBuilder`
- Testy jednostkowe na fixture JSON.

### Faza 2 — Jeden `applyMatchScoringState` (mobile)

- `helpers/matchScoring/applyMatchScoringState.js`
- Stare pliki jako re-exporty do czasu migracji hooków.

### Faza 3 — Transport adapters (mobile)

- `helpers/matchScoring/transports/createTournamentTransport.js`
- `helpers/matchScoring/transports/createFfaTransport.js`
- `helpers/matchScoring/transports/createLocalTransport.js`
- Rozszerzenie `useGameScoringRealtime` o kanał `private` (FFA).

### Faza 4 — `useMatchScoring` (mobile)

- Jeden hook: serializacja zapisów, revision, poll/WS, delegacja do transportu.
- Wrappery zastępują `useGameScoring` / `useQuickGameFfaScoring`.

### Faza 5 — Odchudzenie `GameScoringScreen`

- `resolveMatchContext(route.params)`
- `postMatch.js`, `inputPolicy.js`
- Usunięcie martwego legacy H2H bez visit API.

### Faza 6 — Backend: wspólny kontrakt odpowiedzi API

- Additive: `turn`, `revision`, `format` w obu builderach.
- Mobile preferuje nowe pola, fallback na stare.

### Faza 7 — Backend: wspólny silnik wizyt

- `VisitRecorder` / `LegScoringEngine` — bust, remaining, dartsInVisit, closedLeg.
- Delegacja z `GameScoringService` i `QuickGameFfaScoringService`.

### Faza 8 — (Opcjonalna) Migracja FFA do `game_visits`

- Odłożone — duży scope, po stabilizacji faz 1–7.

### Faza 9 — Cleanup legacy

- `POST /api/game/update` — mecz `FINISHED`: tylko achievementy; bulk finish bez achievementów odrzucony. Mecz `SCHEDULED`: legacy bulk (testy). Quick: `POST /api/quick-game/update`.
- `GameScoringContext::fromQuickGame` — **zostaje** (widok WWW meczu towarzyskiego); nie dotyczy FFA lobby.
- Mobile: re-exporty `applyFfaScoringState` / `useQuickGameFfaScoring` — **usunięte** (faza 5).

### Faza 10 — Dokumentacja

- `LOGIKA_BIZNESOWA.md`, `IMPLEMENTED_FEATURES.md`, `product.md`
- `.cursor/rules/twentysix-engineering.mdc`

## Kolejność PR-ów

| PR | Zakres |
|----|--------|
| #1 | Faza 1+2 — normalize + apply (mobile, bez zmiany zachowania) |
| #2 | Faza 3 — transporty + ujednolicenie WS |
| #3 | Faza 4 — `useMatchScoring` |
| #4 | Faza 5 — `GameScoringScreen` |
| #5 | Faza 6 — kontrakt API backend |
| #6 | Faza 7 — visit engine |
| #7 | Faza 9+10 — cleanup + docs |

## Testy regresji

| Scenariusz | Tryb |
|------------|------|
| Trening 4 graczy, BO3, offline | local |
| Quick FFA 3 graczy, `one_device` | ffa |
| Quick FFA 2 graczy, `each_own`, 2 telefony | ffa |
| Turniej grupa, tablet, suma 3 rzutów | h2h |
| Turniej, per-dart, szybkie 3 kliknięcia | h2h |
| Opuść mecz bez wyniku → `scheduled` | h2h |
| Koniec meczu → alert ze zwycięzcą | wszystkie |
| WS off → poll działa | ffa + h2h |
| WS on → poll wyłączony | ffa + h2h |

## Ryzyka

| Ryzyko | Mitigacja |
|--------|-----------|
| Regresja per-dart / race conditions | Faza 4 kopiuje rdzeń z `useGameScoring` |
| FFA private WS vs public turniej | Jeden realtime hook z parametrem `private` |
| Breaking API | Faza 6 additive only |
| Zbyt duży PR | Każda faza = osobny merge |

## Status implementacji

- [x] Faza 0 — baseline testów (fixture + `npm run test:game-scoring`)
- [x] Faza 1 — `helpers/matchScoring/normalizeScoringState.js`, `computeStateRevision.js`
- [x] Faza 2 — `helpers/matchScoring/applyMatchScoringState.js`, adaptery w starych plikach
- [x] Faza 3 — transporty + `useGameScoringRealtime` z kanałem private (FFA)
- [x] Faza 4 — `useMatchScoring` + cienkie wrappery `useGameScoring` / `useQuickGameFfaScoring`
- [x] Faza 5 — `resolveMatchContext`, `inputPolicy`, `postMatch`, uproszczony `GameScoringScreen`; usunięte stare hooki
- [x] Faza 6 — `ScoringStateContract` (backend): `format`, `turn`, `revision`, `meta` w obu builderach
- [x] Faza 7 — `VisitRecorder`: wspólna logika wizyt (validate, complete, remaining, legs won, turn index); delegacja z `GameScoringService`, `QuickGameFfaScoringService`, builderów
- [ ] Faza 8 (opcjonalna)
- [x] Faza 9 — `POST /api/game/update`: guard na FINISHED; `fromQuickGame` udokumentowany; mobile bez legacy re-exportów
- [x] Faza 10 — dokumentacja zaktualizowana

### Mobile (PR #1 — zrobione)

```
helpers/gameScoring/
  applyGameScoringState.js
  computeStateRevision.js
  normalizeScoringState.js
  inferCurrentPlayerIndex.js
  visitUtils.js
  pid.js
  index.js
  __tests__/runTests.mjs
```

Testy: `npm run test:game-scoring` w `twentysix-mobile`.

### Backend (PR #6 — zrobione)

```
app/Support/GameScoring/VisitRecorder.php
tests/Unit/GameScoring/VisitRecorderTest.php
```

### Mobile (PR #2 — zrobione)

```
helpers/gameScoring/transports/
  createTournamentTransport.js
  createFfaTransport.js
```

- `useGameScoringRealtime` obsługuje `channelType: 'public' | 'private'`.

### Mobile (PR #3+#4 — zrobione)

- `hooks/useGameScoring.js` — wspólny sync (revision, serializacja, poll/WS, visit API).
- `resolveGameContext.js`, `inputPolicy.js`, `postGame.js`
- `GameScoringScreen` — jeden `useGameScoring`, bez wrapperów
- Usunięto: `useQuickGameFfaScoring.js`, stare cienkie adaptery w `helpers/`
