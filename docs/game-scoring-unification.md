# Scoring — architektura (mobile + backend)

**Status:** refaktor **zamknięty** (czerwiec–lipiec 2026). Dokument referencyjny — nie plan prac.

Szczegóły przepływów: [`../LOGIKA_BIZNESOWA.md`](../LOGIKA_BIZNESOWA.md). Konwencje undo: [`../CONVENTIONS.md`](../CONVENTIONS.md).

---

## Cele (osiągnięte)

1. Jeden mental model: mecz = legi + wizyty + sync; kontekst (trening / quick FFA / turniej) to adapter transportu.
2. Jeden hook sync na mobile: `useGameScoring`.
3. Wspólny kontrakt stanu API (`format`, `turn`, `revision`, `meta`) — backend `ScoringStateContract`, mobile `normalizeScoringState`.
4. Wspólny silnik wizyt — backend `VisitRecorder`.

---

## Architektura mobile

```
GameScoringScreen
    └── useGameScoring
            ├── normalizeScoringState / applyGameScoringState
            └── transport
                    ├── createTournamentTransport  (tablet turniej)
                    ├── createFfaTransport           (quick game lobby)
                    └── (trening: brak transportu — lokalny reducer)
```

Pliki: `helpers/gameScoring/`, `hooks/useGameScoring.js`, `resolveGameContext.js`.

Testy reducera: `npm run test:game-scoring` w `twentysix-mobile`.

---

## Co pozostaje osobno (świadomie)

| Obszar | Dlaczego |
|--------|----------|
| Trening | Brak API, brak zapisu |
| Wejście turniej | Kod tabletu, lock/release, lista meczów |
| Wejście quick | Lobby, zaproszenia, `one_device` / `each_own` |
| Po meczu | Standingi/playoff vs statystyki quick vs nic |
| Tabele DB | `game_visits` vs `quick_game_ffa_visits` — merge opcjonalny, odłożony |

---

## WebSocket

| Kontekst | Kanał |
|----------|-------|
| Turniej | public `group-game.*` / playoff |
| Quick FFA | private `private-quick-game-lobby.{lobbyId}` — event `ffa.state.updated` |

`useGameScoringRealtime` — parametr `channelType: 'public' | 'private'`.

---

## Backend

- `GameScoringService` — turniej H2H
- `QuickGameFfaScoringService` — quick FFA 2–8
- `VisitRecorder` — wspólna walidacja wizyt (bust, remaining, closedLeg)
- `tests/Unit/GameScoring/VisitRecorderTest.php`, `tests/Feature/TournamentGameScoringFinalizeTest.php`, `tests/Feature/QuickGameFfaScoringApiTest.php`

---

## Opcjonalna przyszłość (poza scope)

Migracja wizyt FFA do `game_visits` — duży scope; nie planowane w MVP v1.
