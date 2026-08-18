# Design: warianty turnieju SE / DE

**Status:** SE + DE zaimplementowane (sierpień 2026); dopracowanie UX / korekta DE wg feedbacku  
**Źródło prawdy produktu:** [`product.md`](product.md) (sekcja „Warianty turnieju”)  
**Kolejność wdrożenia:** fundament generyczny → **Single elimination** → **Double elimination**

### Stan kodu (sierpień 2026)

| Element | Stan |
| ------- | ---- |
| Generyczny silnik drabinki (string sloty, max 128) | ✅ `PlayoffBracketFactory`, `PlayoffSlotIds` |
| `tournaments.format` | ✅ migracja + `TournamentFormat` |
| SE start (kreator + bye + od razu playoff) | ✅ |
| DE fabryka WB+LB+GF | ✅ `DoubleEliminationBracketFactory` |
| DE start + Grand Final (single/reset) | ✅ |
| DE UI (dwie drabinki) | ✅ `playoff.blade.php` |
| DE miejsca (3 = LB Final, 4 = LB semi) | ✅ `DoubleEliminationPlacement` |

---

## 1. Cel

Dodać dwa warianty turnieju obok istniejącego `groups_playoff`:

| Typ | Opis |
| --- | ---- |
| `single_elimination` | Drabinka SE bez fazy grupowej; bye; mecz o 3. miejsce |
| `double_elimination` | Drabinka DE (WB + LB) bez grup; bye; miejsca z drugiej porażki; Grand Final z wyborem admina |

Jednocześnie: **generyczny silnik drabinki** (max **128**) zamiast enumów `PlayoffSlot` / `WinnerDestinationSlot`, wspólny dla wszystkich typów.

---

## 2. Reguły (skrót — pełne w product.md)

- Wybór typu **tylko przy starcie**; min. **4** graczy.
- Bye: `bracketSize = nextPowerOfTwo(N)`; R1 **losowe**.
- SE: mecz o 3. **tak**; start od razu w fazie pucharowej.
- DE: bez meczu o 3.; UI = **dwie drabinki** + GF; miejsca:

| Miejsce | Kto (przykład 8) |
| ------- | ---------------- |
| 1 | zwycięzca GF (ew. GF2) |
| 2 | przegrany GF (ew. GF2) |
| 3 | przegrany LB Final |
| 4 | przegrany półfinału LB |
| 5+ | wcześniejsze rundy LB (ex aequo) |

- Grand Final: admin wybiera **1 mecz** vs **reset** (fallback implementacyjny: tylko reset).
- `groups_playoff`: reguły grup **bez zmian**; playoff nadal **bez bye**; limit awansu podniesiony do 128 wraz z generycznym silnikiem.

---

## 3. Decyzje architektoniczne

### 3.1 Generyczne sloty (wymagane przed SE)

Zastąpić hardcodowane enumy modelem:

- `bracket_side`: `winners` | `losers` | `grand_final` (SE używa głównie `winners` + slot third)
- `round` (int lub stage label wyliczany)
- `index` w rundzie
- `winner_to_side/round/index/slot` + `loser_to_*` (DE; SE: loser półfinału → third)
- flaga / obsługa **bye** (auto-awans bez meczu)

`PlayoffBracketFactory` generuje drzewo algorytmicznie dla `bracketSize ∈ {4,8,16,32,64,128}` — bez `match` per rozmiar z ręcznymi enumami.

Migracja: istniejące turnieje `groups_playoff` działają na nowym modelu (testy regresji obowiązkowe).

### 3.2 Status turnieju

- SE/DE po starcie: od razu faza pucharowa (ten sam status co dzisiejszy `playoff`, albo jawny alias — bez rozgałęziania scoringu).
- `groups_playoff`: bez zmian (grupy → auto playoff).

### 3.3 Tablet

Bez zmian UX list: płaska lista `oczekujący` + etykieta rundy (`WB R1`, `LB R2`, `O 3. miejsce`, `Finał`, `Finał (reset)`).  
SE/DE: brak kafelków grup.

### 3.4 Scoring H2H

Bez nowego silnika rzutów — tylko generowanie/awans slotów i lista meczów.

---

## 4. Plan A — fundament + Single elimination

### A0. Fundament

1. Zapis w `product.md` ✅ (reguły zamknięte).
2. Model generyczny + migracje DB (kolumny / odejście od enum slotów).
3. Fabryka drzewa 4…128; advance + korekta gałęzi na generycznych wskaźnikach.
4. Przełączenie `groups_playoff` na fabrykę; limit 128 w kreatorze i walidacji.
5. Testy regresji: awans, third, korekta, finish, point scheme, tablet API.

### A1. Kreator (web) — SE

1. Krok typu turnieju.
2. SE: bez grup; podgląd N → X, Y bye; kody tabletów; formaty etapów (bez `GROUP`).
3. Start → puchar od razu.

### A2. Backend SE

1. Losowanie R1 + rozmieszczenie bye.
2. Bye → auto awans.
3. Mecz o 3. z przegranych półfinałów.
4. Miejsca + finish.
5. Testy: 4, 5, 6, 7, 8, 16 (+ smoke większy rozmiar); korekta; walkower.

### A3. UI

1. Web: drabinka SE z bye.
2. Tablet: lista bez grup.
3. Scenariusze manualne w docs.

### A4. DoD (SE)

- Start SE 4–128 z bye; mecz o 3.; korekta; miejsca; punkty.
- `groups_playoff` bez regresji.

---

## 5. Plan B — Double elimination (po SE)

### B0. Design macierzy (przed kodem DE)

Udokumentować w tym pliku (uzupełnić przy starcie B) mapowanie WB→LB dla 4/8/16 i regułę generyczną do 128:

- kolejność rund LB (nie grać, dopóki nie spadł przeciwnik),
- GF / GF2,
- miejsca z drugiej porażki.

### B1. Kreator

- Typ DE + wybór Grand Final (1 mecz / reset); podgląd bye.

### B2. Backend

1. Generacja WB + LB + GF (+ GF2 warunkowo).
2. Advance: WB win → WB; WB loss → LB; druga strata → eliminacja.
3. GF2 tylko przy resecie i wygranej LB w GF1.
4. Korekta (gałęzie WB i LB) — mocne testy.
5. Testy: 4, 6 (bye), 8; oba tryby GF.

### B3. UI

- Web: dwie drabinki + GF.
- Tablet: płaska lista z etykietami stron/rund.

### B4. DoD (DE)

- Pełny flow DE; miejsca zgodne z tabelą; SE i `groups_playoff` bez regresji.

---

## 6. Przykład DE — 8 graczy (referencja)

```
WB R1:  4 mecze (8 graczy)
WB R2:  2 półfinały
WB Final → zwycięzca do GF (0 porażek); przegrany → LB Final

LB R1:  przegrani WB R1 (2 mecze)
LB R2:  zwycięzcy LB R1 + przegrani WB R2
LB R3:  półfinał LB (1 mecz)
LB Final: zwycięzca LB R3 vs przegrany WB Final → zwycięzca do GF

Grand Final: WB champ vs LB champ
  - tryb 1 mecz: koniec
  - tryb reset: jeśli wygra LB → GF2
```

Bye przy N≠potędze 2: jak SE w WB R1; wpływ na LB według macierzy z B0.

---

## 7. Kolejność prac

```text
product.md (✅) → generyczny bracket max 128 → SE end-to-end → macierz DE → DE end-to-end
```

**Nie zaczynać implementacji DE przed DoD SE.**  
**Nie rozszerzać limitu enumami** — tylko generyczny model.

---

## 8. Poza zakresem tej pracy

- Seeding z rankingu sezonowego
- Bye w playoffie po grupach
- Zmiana scoringu H2H / FFA
- Deploy / EAS (po stronie właściciela)
