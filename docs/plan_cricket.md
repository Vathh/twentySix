# Plan: Cricket (standard / scoring)

**Status:** ✅ fazy 1–3 (lipiec 2026)  
**Produkt:** [`product.md`](product.md) · backlog: [`NEXT_STEPS.md`](NEXT_STEPS.md)

---

## 1. Zakres produktu (ustalone)

| Reguła | Decyzja |
|--------|--------|
| Wariant | **Standard / scoring cricket** — bez cut-throat |
| Pola | 20, 19, 18, 17, 16, 15, bull (25) |
| Koniec lega | Gracz ma **wszystkie pola zamknięte** (≥3 marki) **oraz** **ściśle więcej punktów** niż każdy inny gracz. Remis punktów → leg trwa. |
| Punkty | Po zamknięciu pola kolejne marki dają punkty, dopóki **co najmniej jeden** przeciwnik ma pole otwarte. |
| Konteksty | **Trening** + **quick game** FFA 2–8. **Bez turnieju.** |
| Tryby urządzeń | `one_device` i `each_own` |
| Format | Tylko **legi**. `setsToWinMatch = 1`. |
| Opener | Rotacja `(opener + 1) % N` po legu. |

---

## 2. Fazy

### Faza 1 — Trening offline ✅

Lokalny `CricketGameScoringScreen` + `helpers/cricket/`.

### Faza 2 — Quick `one_device` ✅

Lobby + sync API (host wpisuje; goście widzą stan na żywo). Zapis wyniku w `quick_games` / `quick_game_results` po zakończeniu.

### Faza 3 — Quick `each_own` ✅

- Endpointy: `POST …/ffa/cricket/darts`, `POST …/ffa/cricket/darts/undo`
- Stan: `GET …/ffa/state` → `format: ffa_cricket` (gdy `game_type=cricket`)
- Persystencja: `quick_game_ffa_sessions.cricket_state` (JSON boards + dartLog)
- Mobile: `createFfaCricketTransport` + `useCricketFfaScoring`
- **Nie** używamy X01 `submitVisit`

---

## 3. Architektura

**Backend**

- `App\Support\QuickGameFfa\CricketRules`
- `App\Services\QuickGame\QuickGameFfaCricketScoringService`
- Migracja `cricket_state` na `quick_game_ffa_sessions`

**Mobile**

```
helpers/cricket/
components/Game/CricketGameScoringScreen.jsx
helpers/gameScoring/transports/createFfaCricketTransport.js
hooks/useCricketFfaScoring.js
```

---

## 4. Poza zakresem

- Turniej / tablet / live web cricket
- Cut-throat

---

*Cricket MVP (trening + quick obu trybów) — domknięte.*
