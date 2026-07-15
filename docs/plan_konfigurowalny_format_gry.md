# Plan: konfigurowalny format gry (sety / legi / punkty)

**Status:** ✅ **fazy 1–4 wdrożone** (lipiec 2026) · Faza 5 opcjonalna  
**Ostatnia aktualizacja:** lipiec 2026

---

## 0. Ustalenia produktowe (lipiec 2026)

### Faza testowa — pełny remodel

- **Brak kompatybilności wstecznej** z `legs_count`, starymi polami BO3 itp.
- Można **przemodelować schemat DB i API** od zera (`migrate:fresh` na dev/staging OK).
- Stare kolumny **zastępujemy**, nie aliasujemy.

### Preset domyślny (wszędzie)

| Sety / mecz | Legi / set | Punkty |
|-------------|------------|--------|
| **1** | **2** | **501** |

= dotychczasowe BO3 w nowym modelu (`setsToWinMatch: 1`, `legsToWinSet: 2`, `startingScore: 501`).

### Turniej — tablet zero konfiguracji

Admin ustawia format **tylko przy starcie turnieju** (web). Przy generowaniu meczów format trafia **na rekord meczu** (`games` / `playoff_games`).

**Tablet nie ustawia nic** — po locku meczu scoring API zwraca `matchFormat` z tego rekordu; aplikacja startuje grę z gotowymi parametrami.

`tournament_match_formats` = konfiguracja turnieju (kreator startu); **źródło przy tworzeniu meczów**. Runtime scoringu = **wyłącznie snapshot na meczu**.

### Trening vs quick game — ostatnio używane ustawienia

| Kontekst | Gdzie pamiętamy | Klucz (mobile, AsyncStorage) |
|----------|-----------------|------------------------------|
| **Trening** | Lokalnie na urządzeniu | `@twentysix/trainingMatchFormat` |
| **Quick game lobby** | Lokalnie na urządzeniu | `@twentysix/quickGameMatchFormat` |

- Przy wejściu w trening / lobby host widzi **ostatnio używany format** w tym kontekście.
- **Osobne pamięci** — ustawienia treningu **nie** przechodzą na quick game i odwrotnie.
- Pierwsze uruchomienie (brak zapisu) → preset domyślny (501 / 1 set / 2 legi).
- Backend quick game nadal zapisuje format w lobby/sesji FFA (sync online); „ostatnio używane” to **UX hosta na telefonie**, nie preferencje konta w chmurze.

---

## 1. Cel produktowy

Umożliwić konfigurację **dystansu gry** zamiast stałego BO3 / 501:

| Parametr | Znaczenie | Przykład |
|----------|-----------|----------|
| **Punkty startowe lega** | Od ilu zaczyna się leg (X01) | 301, 501, 701 |
| **Legi do wygrania seta** | Pierwszy do N legów wygrywa set | 3 |
| **Sety do wygrania meczu** | Pierwszy do M setów wygrywa mecz | 2 |

**Trening + quick game:** host ustawia format w **lobby / ekranie treningu** przed startem.

**Turniej:** organizator ustawia format **osobno dla każdego etapu** turnieju w **kreatorze startu** na webie.

---

## 2. Kontrakt `MatchFormat` (wdrożony)

```json
{
  "gameType": "x01",
  "startingScore": 501,
  "legsToWinSet": 2,
  "setsToWinMatch": 1,
  "outRule": "double_out"
}
```

**Gdy `setsToWinMatch === 1`:** w UI skrót „501 · do N legów” bez słowa „set”.

**Hierarchia:** Mecz → Set → Leg → Wizyty.

**H2H / FFA:** ten sam silnik (`MatchFormatScoring`); FFA kończy się gdy **ktokolwiek** osiągnie `setsToWinMatch`.

---

## 3–4. Turniej, trening, quick, walkower — wdrożone

Szczegóły produktowe: [`product.md`](product.md) (sekcja „Format gry”).

Kluczowe miejsca w kodzie:

| Warstwa | Pliki |
|---------|--------|
| Backend | `MatchFormat`, `MatchFormatScoring`, `GameScoringService`, `QuickGameFfaScoringService`, `TournamentMatchFormatRequestParser`, `GameLegScoreValidator` |
| Mobile | `helpers/matchFormat/`, `MatchFormatPicker`, `GameScoringScreen`, `Counter`, reducery |
| Web | `tournaments/start.blade.php`, `games/show`, `games/live` + `gameLiveViewer.js` |

API lobby: źródłem prawdy jest `matchFormat` (`startingScore`, `legsToWinSet`, `setsToWinMatch`).

---

## 5. Fazy realizacji

### Faza 1 — Kontrakt `MatchFormat` + remodel DB ✅

- [x] Value object `MatchFormat` (backend + mobile)
- [x] Migracje: kolumny na `games`, `playoff_games`, lobby, FFA session; usunięcie `legs_count`
- [x] API scoring: `meta.matchFormat`, `setsWon`, `legsWonInSet`, `currentSetNumber`
- [x] Domyślne wartości = preset **501 / 1 / 2**
- [x] Testy na nowym modelu

### Faza 2 — Trening + quick game ✅

- [x] UI: 3 pola + AsyncStorage (osobno trening / quick)
- [x] Lobby API + start FFA z `MatchFormat`
- [x] Silnik FFA: leg → set → mecz
- [x] Trening offline: sety w reducerze lokalnym

### Faza 3 — Turniej ✅

- [x] `tournament_match_formats` + kreator web (wiersze per `GameStage`)
- [x] Snapshot formatu na każdym `Game` / `PlayoffGame`
- [x] Tablet: format z rekordu meczu (bez konfiguracji)
- [x] Etykiety web live / Counter multi-set

### Faza 4 — Walkower, korekta, dokumentacja ✅

- [x] `GameLegScoreValidator` + formularz korekty web
- [x] `product.md` — sekcja „Format gry”
- [x] Scenariusze manualne (aktualizacja checklist)
- [x] Cleanup: API / mobile wyłącznie `matchFormat` (bez aliasu `legsCount`)

### Faza 5 — (opcjonalnie później) 📋

- [ ] Presety formatów w lidze (domyślne dla nowych turniejów)
- [ ] BO5/BO7 jako szybkie presety w lobby / treningu
- [ ] Cricket z własnym formatem (poza X01)

---

## 6. Faza 5 — co to oznacza (szczegóły)

**Nie są to braki w silniku scoringu** — fazy 1–4 już pozwalają ustawić np. „do 3 legów” albo „2 sety × 3 legi”. Faza 5 to **UX / produkt ponad to**:

### 5.1 Presety formatów w lidze

Dziś admin przy **każdym** starcie turnieju wypełnia tabelę etapów (domyślnie 501/1/2 w każdym wierszu).

Docelowo: w ustawieniach **ligi** zapisane „standardy” (np. grupy = do 2 legów, finał = do 5), które kreator turnieju **wczytuje jako start**. Admin nadal może nadpisać per turniej. Oszczędza kliknięcia przy serii turniejów tej samej ligi.

### 5.2 BO5 / BO7 jako szybkie presety

Dziś host kręci trzema pickerami (punkty / legi / sety).

„BO5” / „BO7” to skróty UI: jeden przycisk = `setsToWinMatch: 1` + `legsToWinSet: 3` (BO5) lub `4` (BO7). Nie nowa logika — tylko chipy presetów obok pickera. (W dacie „pierwszy do N” BO5 = do 3 legów.)

### 5.3 Cricket

Osobny `gameType` z innym silnikiem punktacji (nie X01). Plan konfigurowalnego formatu **X01 nie obejmuje** krykieta — to duży, osobny feature (świadomie poza MVP, jak w `product.md`).

---

## 7. Walidacja i limity (MVP)

| Pole | Min | Max | Uwagi |
|------|-----|-----|--------|
| `startingScore` | 101 | 1001 | Picker: 101…1001 co 100 |
| `legsToWinSet` | 1 | 15 | „Pierwszy do N” |
| `setsToWinMatch` | 1 | 5 | |

**Double out** — jedyna reguła checkoutu.

---

## 8. Checklist testów manualnych

- [ ] Trening: 301, 2 sety, 3 legi — reset po legu/secie; wynik końcowy w setach
- [ ] Quick FFA 4P: ten sam format, rotacja openera między legami
- [ ] Turniej grupa: format z kreatora na tablecie
- [ ] Playoff: różne `legsToWinSet` na kolejnych rundach
- [ ] Walkower web: wynik zgodny z formatem meczu (legi vs sety)
- [ ] Korekta wyniku: walidacja odrzuca niemożliwe wyniki
- [ ] Live web: przy multi-set widać sety + legi w secie
- [ ] Undo lega przy granicy seta

---

## 9. Powiązane pliki

- [`product.md`](product.md) — sekcja „Format gry”
- [`game-scoring-unification.md`](game-scoring-unification.md)
- `app/Support/GameScoring/MatchFormat.php`
- `helpers/matchFormat/` (mobile)
- `components/QuickGame/MatchFormatPicker.jsx`

---

*Fazy 1–4 zamknięte. Dalszy rozwój opcjonalny: **Faza 5** (presety ligi, chipy BO5/BO7, cricket).*
