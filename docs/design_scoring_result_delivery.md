# Design: odporność dostarczenia wyniku scoringu (turniej → FFA)

**Status:** ✅ wdrożone (sierpień 2026)  
**Cel:** po utracie sieci na tablecie / telefonie wynik meczu i tak trafia do API albo tablet rozpoznaje, że serwer już go ma.  
**To nie jest** anty-cheat / walidacja „kto może wysłać rezultat”.

Źródło planu: backlog „Zabezpieczenie wysyłki rezultatu gry” w [`NEXT_STEPS.md`](NEXT_STEPS.md).

---

## 1. Problem

Turniej (tablet): checkout → `recordVisit(closedLeg)` + `closeLeg` → timeout / brak sieci.

| Dziś | Docelowo |
|------|----------|
| Alert; brak kolejki; brak flush po reconnect | Outbox + flush po sieci |
| `closeLeg` przy już zamkniętym legu → błąd | Idempotentny `closeLeg` → aktualny stan |
| WS reconnect tylko ustawia `wsHealthy` | + `fetchState` + flush outbox |

FFA: koniec meczu w `recordVisit` (bez osobnego `closeLeg`); retry po `clientVisitId` nie może ponownie wywołać `applyTurnAfterVisit`.

---

## 2. Faza A — Turniej (group / playoff)

### A1. Backend — idempotentne `closeLeg`

`GameScoringService::closeLeg`:

- Leg już zamknięty → zwrócić stan meczu (jak sukces), **bez** ponownego `finishLeg` / finalize.
- Finalize (`finalizeTournamentGameFromScoring`) tylko przy pierwszym przejściu do `FINISHED`.

HTTP bez zmiany kontraktu (`GroupGameScoringController` / `PlayoffGameScoringController`).

### A2. Mobile — outbox

Helper `helpers/gameScoring/scoringOutbox.js` (AsyncStorage):

- Klucz: `scoring-outbox:tournament:{kind}:{gameId}`
- FIFO: `{ op: 'recordVisit'|'closeLeg', legId, payload, clientVisitId?, createdAt }`
- Enqueue tylko przy błędach **retryable** (sieć / 5xx / timeout), nie przy 4xx walidacji
- Flush: NetInfo online, `pusher:subscription_succeeded`, AppState `active`
- Przed flush: `fetchState` — jeśli `finished`, wyczyść outbox i `setGameClosed(true)`

Integracja w `useGameScoring` (serialized writes).

### A3. WS reconnect

`useGameScoringRealtime`: callback `onResubscribed` → fetchState + flush.

### A4. Achievementy

Po `gameClosed`: retry wysyłki achievements; backend bez duplikatów insertów. Nie blokuje wyniku meczu.

---

## 3. Faza B — FFA

### B1. Backend

Przy istniejącym `clientVisitId` i już kompletnej wizycie (bust / closedLeg / 3 darts) → **tylko** zwrócić stan, bez `applyTurnAfterVisit`.

### B2. Mobile

Ten sam outbox, klucz `scoring-outbox:ffa:{lobbyId}`, operacje `recordVisit`.

---

## 4. Scenariusze akceptacyjne

1. Ostatni `closeLeg` nie dochodzi → po Wi‑Fi flush → mecz `FINISHED` na webie.
2. Serwer przyjął close, tablet stracił odpowiedź → reconnect `fetchState` → UI końca.
3. Mid-match `recordVisit` fail sieciowy → flush, upsert `clientVisitId` bez duplikatu.
4. Błąd walidacji → bez enqueue.
5. FFA: retry tej samej kompletnej wizyty → bez podwójnego awansu tury.

---

## 5. Poza zakresem

- Pełny offline scoring turnieju bez sieci
- Anty-fałszowanie wyniku z klienta
- Nowe endpointy finalize
