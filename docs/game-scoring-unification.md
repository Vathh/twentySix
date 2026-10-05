# Scoring — architektura (mobile + backend)

**Status:** refaktor scoringu zamknięty (czerwiec–lipiec 2026). Od października 2026 pisarz H2H i host `one_device` nie sterują meczem przez WebSocket.

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

Pisarz nie słucha stanu meczu. Ekran sędziego (tablet i `/referee`) oraz host quick game `one_device` wysyłają komendy HTTP. Odpowiedź komendy aktualizuje tablicę. Brak sieci odkłada komendę w kolejce i wysyła ją po powrocie łącza. Widzowie i tryb `each_own` zostają na sockecie.

| Kontekst | Połączenie |
|----------|------------|
| Turniej / liga, ekran sędziego | HTTP + kolejka komend. Heartbeat locka (grupa/playoff) bez podmiany tablicy |
| Host quick game `one_device` | HTTP + kolejka komend |
| Gość `one_device`, gracze `each_own` | private `private-quick-game-lobby.{lobbyId}` — `ffa.state.updated` |
| Podgląd na stronie turnieju | public `group-game.*` / playoff — `game.state` |

`useGameScoringRealtime` — tylko słuchacze (`channelType: 'public' | 'private'`). Backup poll pełnego stanu startuje wyłącznie wtedy, gdy transport prosi o realtime i socket padł.

---

## Backend

- `GameScoringService` — turniej H2H
- `QuickGameFfaScoringService` — quick FFA 2–8
- `VisitRecorder` — wspólna walidacja wizyt (bust, remaining, closedLeg)
- `tests/Unit/GameScoring/VisitRecorderTest.php`, `tests/Feature/TournamentGameScoringFinalizeTest.php`, `tests/Feature/QuickGameFfaScoringApiTest.php`

---

## Opcjonalna przyszłość (poza scope)

Migracja wizyt FFA do `game_visits` — duży scope; nie planowane w MVP v1.
