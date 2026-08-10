# Plan: cykl życia lobby quick game

**Status:** ✅ wdrożone (sierpień 2026)  
**Backlog:** [`NEXT_STEPS.md`](NEXT_STEPS.md) — „Usuwanie niepotrzebnych lobby”

## Cel

1. Użytkownik ma **co najwyżej jedno aktywne lobby** (`waiting` | `started`) — jako host lub gracz.
2. Akceptacja zaproszenia do cudzego lobby **usuwa** własne `waiting` (host).
3. Drugi telefon nie zakłada drugiego lobby — **409** + `existingLobbyId`.
4. Scheduler czyści martwe `waiting` (TTL).

## Reguły

| Akcja | Zachowanie |
|-------|------------|
| `create` przy istniejącym `waiting`/`started` | **409** + `existingLobbyId`, `status` |
| `join` przy `started` | **409** |
| `join` przy własnym `waiting` (host) | delete własnego lobby, potem join |
| `join` jako gość w innym `waiting` | removePlayer z tamtego, potem join |
| `createRematch` | zwolnij inne `waiting` hosta; przy `started` → 409 |

`finished` nie liczy się jako aktywne.

## Cleanup

`php artisan quick-game:prune-lobbies` — co godzinę: hard delete `waiting` z `updated_at` starszym niż **6 godzin**.

## Poza zakresem MVP

- Agresywne kasowanie stale `started` (ryzyko wyniku)
- Unikalny indeks SQL na aktywne lobby
