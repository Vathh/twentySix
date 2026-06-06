# twentySix — wizja produktu

> **Nazwa produktu:** **twentySix** (wszędzie — UI, sklepy, dokumentacja, kod).  
> **Logo / ikona:** znak **26** (żart graczy: 1, 20 i 5) — wyłącznie warstwa wizualna, nie nazwa produktu. Szczegóły: [Marka produktu](#marka-produktu).  
> Foldery repozytoriów: **`twentysix-backend`**, **`twentysix-mobile`** (instrukcja rename: [`RENAME_FOLDERS.md`](RENAME_FOLDERS.md)).

System do organizacji i prowadzenia lig oraz meczów bezpośrednich darterskich: aplikacja webowa do śledzenia i zarządzania ligami wraz z API, aplikacja mobilna do sędziowania meczów. (Porównanie: chess.com, ale dla darta.)

## Źródło prawdy i stan kodu

**`product.md` jest źródłem prawdy** dla wszystkich decyzji produktowych i implementacyjnych.

Część kodu twentySix (web/API i mobile) powstała **przed** pracą z Cursorem i bez tego dokumentu — stąd **rozbieżności** między kodem a opisem produktu. To **nie zmienia wymagań**: kod, który odbiega od `product.md`, należy **doprowadzić do zgodności** z dokumentem (refaktoryzacja / uzupełnienie), a nie odwrotnie.

Szczegółowa lista znanych rozbieżności: [Uwagi dla implementacji](#uwagi-dla-implementacji-stan-kodu-vs-produkt).  
Mapa postępu MVP: [`IMPLEMENTED_FEATURES.md`](../IMPLEMENTED_FEATURES.md) (backend), [`../twentysix-mobile/IMPLEMENTED_FEATURES.md`](../twentysix-mobile/IMPLEMENTED_FEATURES.md) (mobile).

## Dla kogo

- Organizatorzy turniejów/lig darterskich
- Gracze rywalizujący w turniejach oraz bezpośrednich starciach
- Kibice/zainteresowani wynikami

## Problem, który rozwiązujemy

Istnieje podobny system: [n01darts.com](https://n01darts.com/n01/). Brakuje w nim m.in.:

- stałego konta gracza i „postaci” ze statystykami,
- udziału w turniejach zawsze jako ten sam użytkownik,
- lig jako serii turniejów z tabelą ligową.

W n01 gracze turniejowi są tymczasowymi nazwami bez przypisanych osiągnięć i historii poza danym turniejem.

## Wizja (efekt końcowy)

### Niezarejestrowany

- **Web:** przeglądanie publicznych lig, turniejów, wyników, tabel i statystyk (docelowo także pojedynczych meczów).
- **Mobile:** mecze **offline** bez zapisu do bazy i bez historii (w tym mecz ćwiczeniowy solo).

### Zarejestrowany

- Wszystko, co gość na webie.
- Znajomi (zaproszenie + akceptacja — **MVP: mobile**).
- Własne statystyki i historia (turnieje + quick game online).
- **Mobile:** lobby quick game, akceptacja zaproszeń (turniej, lobby, znajomi), tablet turniejowy, offline/solo ćwiczenia.
- Docelowo: znajomi i komunikator na webie, odznaczenia, link live quick game.

### Premium (docelowo)

- Wszystko, co użytkownik zarejestrowany.
- Tworzenie lig/turniejów (w MVP: rola **organizatora w lidze**).

### Publiczność vs uczestnik turnieju

- Ten sam zakres podglądu turnieju.
- Zarejestrowany uczestnik: zapis w **własnej historii i statystykach** po turnieju.

### Gracz tymczasowy (gość turniejowy)

- Brak powiązania z kontem i brak merge statystyk.
- Statystyki tylko w meczu/turnieju/lidze — nigdy w profilu.
- Dodawanie: **nazwa** wpisana przez admina.

### Cykl życia (skrót)

Rejestracja → znajomi (mobile) → quick game → twórca ligi = organizator → turniej → zaproszenia (akceptacja mobile) → start (grupy, awans, kody) → losowy podział do grup → mecze → playoff → punkty ligowe.

### Rozgrywka turniejowa

Zaproszenia (web) + akceptacja (mobile) + goście → **start** możliwy **bez pełnej akceptacji** — w puli tylko **zaakceptowani + goście** → kreator startu → round-robin → tablet → playoff.

**Korekta wyniku:** ręczny poprawny wynik na webie (w tym walkower) → auto przeliczenie playoff i tabel.

### Rozgrywka poza turniejem

Quick game: FFA (każdy gra sam), max **8** graczy, 501 BO3, wybór **jedno urządzenie** vs **każdy na własnym** (patrz niżej). Start lobby możliwy bez pełnej akceptacji — grają tylko **zaakceptowani**.

## Tryby meczów (podsumowanie)

| Tryb                      | Zapis w bazie | Live (WebSocket) |
| ------------------------- | ------------- | ---------------- |
| Quick game online (lobby) | tak           | tak              |
| Turniej (kod tabletu)     | tak           | tak              |
| Offline / solo ćwiczenia  | nie           | nie              |

## Status meczu turniejowego

| Status       | Tablet (lista do rozegrania) |
| ------------ | ---------------------------- |
| `oczekujący` | Widoczny, można wybrać       |
| `w trakcie`  | Ukryty                       |
| `zakończony` | Ukryty                       |

### Start meczu z tabletu

Wybór → API → `w trakcie` (lock); inne tablety nie widzą meczu; race → błąd API; koniec → `zakończony`.

**Turniej na tablecie** = zawsze tryb **jednego urządzenia** (sędzia wpisuje rzuty obu zawodników head-to-head).

## Role i uprawnienia

| Rola                   | MVP                                                                 | Docelowo               |
| ---------------------- | ------------------------------------------------------------------- | ---------------------- |
| Gość (web)             | Podgląd lig/turniejów                                               | + pojedyncze mecze     |
| Gość (mobile)          | Offline, solo ćwiczenia                                             | bez zmian              |
| Użytkownik             | Quick game, turnieje, znajomi (zaproszenia/akceptacja **mobile**)   | + komunikator, web     |
| **Organizator**        | **Twórca ligi = organizator**; uprawnienia w lidze                  | premium                |
| **Współadministrator** | Pełne prawa, cała liga (MVP)                                        | granularne uprawnienia |
| Sędzia (tablet)        | Kod turnieju, wybór meczu                                           | —                      |

### Kody logowania (tablety)

Start turnieju: liczba kodów = liczba tabletów; jeden kod = jeden tablet; bez konta użytkownika.

### Tablet — wybór meczu

Tylko `oczekujący`. **Grupy:** kafelki grup → mecze. **Playoff:** płaska lista (zawodnicy + runda).

## Turniej — logika (MVP)

### Uczestnicy przy starcie

- Admin może **wystartować turniej** (i quick game), nawet gdy **nie wszyscy** zaproszeni zaakceptowali.
- Do gry wchodzą **wyłącznie** zawodnicy ze **statusem zaakceptowanym** + **goście** (nazwa od admina).
- Niezaakceptowani zaproszeni **nie są brani pod uwagę** (nie trafiają do losowania grup).

### Minimum zawodników

| Kontekst   | Minimum | Kogo liczymy                          |
| ---------- | ------- | ------------------------------------- |
| Turniej    | **4**   | zaakceptowani + goście (łącznie)      |
| Quick game | **2**   | zaakceptowani zaproszeni do lobby     |

Bez spełnienia minimum start jest **zablokowany** (UI + walidacja API).

### Start turnieju (kreator na webie)

1. **Liczba grup** — dowolna **potęga 2** (2, 4, 8, 16, 32, 64, …). Nie ma sztywnej listy ani górnego limitu poza rozsądkiem turnieju (np. liczba zawodników); walidacja API/UI akceptuje wyłącznie wartości `2^n`.
2. **Awans z grupy** — wartości **walidowane**: `grupy × awans` musi być **potęgą 2** (pełna drabinka playoff, bez wolnych losów). Awans z grupy też musi być potęgą 2 (1, 2, 4, …). Np. 8 grup → awans 1, 2 lub 4 (nie 3); 64 grupy → awans 1 lub 2 (bo 64×4=256 — OK, ale 64×3 — odrzucone).
   - **MVP:** maksymalnie **32 awansujących** do drabinki (`grupy × awans ≤ 32`). Większe turnieje (np. 64×2) — po MVP.
3. **Liczba kodów na tablety**.
4. Losowy podział puli zawodników do grup (reguła wielkości — patrz niżej).
5. Round-robin w każdej grupie.

### Podział zawodników do grup

- Losowanie zawodników, ale **zapełnianie grup od nr 1** w górę.
- Grupy **maksymalnie równe**; nadmiar rozdzielany tak, że większe grupy są **wcześniejsze** (niższe numery), mniejsze **późniejsze**.
- Przykład: **30 zawodników, 8 grup** → rozkład **4, 4, 4, 4, 4, 4, 3, 3** (nie 3,3,4,4,4,4,4,4).

### Tabela grupowa i awans

Kolejność miejsc w grupie (tie-breakery):

1. **Liczba punktów** (malejąco)
2. **Różnica legów** (malejąco)
3. **Mecz bezpośredni** między zawodnikami z remisu
4. **Losowanie** (gdy bezpośredni mecz nie rozstrzyga lub brak meczu bezpośredniego)

Logika zgodna z `GroupStandingService` w backendzie (`sortStandings` → `compareByDirectGame` → `shuffle`).

### Grupy i playoff

- Round-robin: każdy z każdym w grupie.
- **Playoff startuje automatycznie** po rozegraniu **ostatniego meczu grupowego** (status turnieju → playoff).
- Drabinka: pełna, **bez wolnych losów**; auto przeliczenie po korekcie wyniku na webie.

### Losowanie par playoff (pierwsza runda)

- Pary w **pierwszej rundzie** playoff dobierane **losowo** z puli awansujących.
- **Ograniczenie:** w pierwszym meczu playoff **nie mogą** trafić na siebie **dwaj zawodnicy z tej samej grupy** (unikamy natychmiastowego re-matchu po fazie grupowej).
- Algorytm: losowanie z **ponowieniem / zamianą par**, aż układ spełnia warunek (lub deterministyczne tasowanie z walidacją — implementacja dowolna, efekt ten sam).
- Kolejne rundy playoff: standardowe wynikanie z drabinki (zwycięzca → następna runda).

### Walkower i korekta wyniku

- Admin na webie wchodzi w mecz i wpisuje **poprawny wynik** (nie osobny flow „cofnij”).
- **Walkower:** wynik **do zera** — przy BO3 = **2:0** w legach (zwycięzca : przegrany).
- Po zapisie: **automatyczne przeliczenie** dalszych rund playoff i tabel.

### Achievementy

Działają poprawnie w MVP w meczu turniejowym (180, 170+, QF, HF itd.).

### Zaproszenia do turnieju

- Wysyłka: admin na **webie** (wyszukiwarka).
- Akceptacja: **mobile**.

## Liga i punktacja (MVP)

- Liga = seria turniejów + tabela ligowa + tabele per turniej.
- **Point scheme** narzucany przez system według **liczby graczy w turnieju** (schematy w kodzie).

## Znajomi i quick game

### Znajomi (MVP)

- Zaproszenie + akceptacja — **wyłącznie mobile** (wysyłka i akceptacja).
- Docelowo: także **web** (przy komunikatorze).

### Quick game (MVP)

- Lobby zakłada zalogowany użytkownik; zaproszenia — akceptacja na **mobile**.
- Tylko **znajomi**; max **8** zawodników.
- **FFA** — każdy gra sam (1v1, 1v1v1v1…; nie drużyny 2v2).
- Format: **501 double out, BO3** (do **2 wygranych legów**).
- **Zwycięzca meczu:** zawodnik, który **pierwszy wygra 2 legi** (niezależnie od liczby uczestników — 2, 3, 4…).
- **Kolejność zawodników** ustawiana w lobby (np. A, B, C, D).
- **Kolejność rzutów w legu:** zaczynający leg → następny w kolejce → … (cyklicznie).
- **Kto zaczyna kolejny leg:** zawsze **następny zawodnik po tym, który zaczynał poprzedni leg** — **niezależnie od tego, kto wygrał leg**.
  - Leg 1 zaczyna **A** → kolejność rzutów: **A → B → C → D** (i dalej cyklicznie do końca lega).
  - Leg 2 zaczyna **B** (następny po A) → kolejność: **B → C → D → A** — nawet jeśli leg 1 wygrał np. B lub D.
  - Leg 3 zaczyna **C** (następny po B) → kolejność: **C → D → A → B** — nawet jeśli leg 2 wygrał np. D.
  - Leg 4 (jeśli potrzebny przy BO3) zaczyna **D** → kolejność: **D → A → B → C**.
- Start bez pełnej akceptacji — grają tylko **zaakceptowani** zaproszeni.
- **Minimum 2** zaakceptowanych zawodników do startu.
- Wyniki w statystykach gracza.

### Quick game — tryb urządzeń (wybór w lobby)

Już istniejący wybór w lobby mobilnym:

| Tryb | Zachowanie |
| ---- | ---------- |
| **Jedno urządzenie** | Jedna osoba na jednym telefonie/tablecie wpisuje rzuty **wszystkich** zawodników (jak sędziowanie). |
| **Każdy na własnym urządzeniu** | Ten sam widok meczu na każdym telefonie, **synchronizacja przez API** + WebSocket. Zawodnik wpisuje rzuty **tylko w swojej kolejce** — czeka na turę, nie gra równolegle. |

- Turniej na tablecie = zawsze model **jednego urządzenia** (head-to-head).
- **Krykiet** — poza MVP (kod w toku, wrócimy później).

### Quick game (docelowo)

- Dowolny zalogowany w lobby; **krykiet** i inne formaty.
- Konfigurowalna liczba legów do wygranej (nie tylko BO3).

## Reguły meczu (MVP)

| Kontekst            | Gra                 | Format MVP          |
| ------------------- | ------------------- | ------------------- |
| Turniej (tablet)    | 501 head-to-head    | 501 double out, BO3 (do 2 legów) |
| Quick game          | 501 multi FFA       | 501 double out, BO3 (pierwszy do 2 legów) |
| Offline / ćwiczenia | 501                 | bez zapisu          |
| Krykiet             | —                   | poza MVP            |

Ten sam silnik liczenia i model wyniku w API (turniej + quick game 501).

## Zakres systemu

- **Web (twentySix):** ligi/turnieje, start turnieju, zaproszenia do turnieju (wysyłka), korekta wyników, live, publiczny podgląd. Znajomi — poza MVP na webie.
- **API:** walidacja grup×awans, podział do grup, statusy meczów, quick game (oba tryby urządzeń), zaproszenia, achievementy, point schemes.
- **Mobile:** tablet, quick game, znajomi (MVP), akceptacja zaproszeń (turniej, lobby, znajomi), offline/solo.

## MVP (wersja 1 — musi działać)

### Web

- Twórca ligi = organizator; współadmin per liga
- Turniej: zaproszenia, goście, start (grupy + walidowany awans + kody)
- Start z podzbiorem zaakceptowanych zawodników
- Korekta wyniku / walkower (np. 2:0 przy BO3); live WebSocket

### API

- Pula startowa = zaakceptowani + goście; min. 4 (turniej) / min. 2 (quick game)
- Walidacja drabinki; równy podział do grup od grupy 1
- Tie-breakery grupowe; losowanie playoff z unikaniem par z tej samej grupy (runda 1)
- Auto start playoff po ostatnim meczu grupowym
- Quick game: FFA do 8 graczy; single-device + multi-device (kolejka tur)
- Achievementy, auto point scheme

### Mobile

- Znajomi (zaproszenie + akceptacja)
- Akceptacja turniej + lobby
- Quick game: wybór urządzeń, FFA, 501 BO3
- Tablet turniejowy

## Poza MVP (świadomie później)

- Krykiet
- Znajomi na webie, komunikator, odznaczenia, stream, premium
- Granularne uprawnienia współadmina
- Quick game z dowolnym zalogowanym
- Publiczny podgląd pojedynczych meczów
- Konfigurowalne formaty / tryby turniejów; **liczba legów do wygranej** (nie tylko BO3)
- **Drabinka playoff > 32 awansujących** — w MVP limit `grupy × awans ≤ 32`. Rozszerzenie: refaktor slotów playoff na **generyczne** (`round` + `index` zamiast enumów `PlayoffSlot` / `WinnerDestinationSlot`), żeby skalować do 64+ awansujących bez eksplozji enumów. **Implementacja: opcja B** — zaplanować po domknięciu MVP turniejowego.

## Czego nie robimy (na razie)

- Krykiet w MVP
- Tryby drużynowe 2v2 w quick game
- Znajomi na webie (MVP)
- Wolne losy w drabince
- Równoległe wpisywanie rzutów w multi-device (tylko kolejno)

## Kryterium „MVP jest gotowe”

1. Znajomi + zaproszenia turniej/lobby — akceptacja na mobile.
2. Start turnieju/quick game bez pełnej akceptacji; gra tylko zaakceptowani (+ goście w turnieju).
3. Turniej min. 4 zawodników (łącznie z gośćmi); grupy, tie-breakery, round-robin; playoff bez bye; losowanie rundy 1 bez par z jednej grupy.
4. Tablet + live web; achievementy; auto start playoff.
5. Quick game min. 2; do 8 graczy FFA; pierwszy do 2 legów; rotacja startu legów (następny po openerze poprzedniego lega, bez względu na zwycięzcę); oba tryby urządzeń; statystyki.
6. Walkower/korekta na webie (2:0) → auto przeliczenie; point scheme z liczby graczy.
7. Offline/solo bez zapisu; gość ogląda ligi/turnieje.

## Marka produktu

| Kontekst | Nazwa | Uwagi |
| -------- | ----- | ----- |
| **Produkt (wszędzie)** | **twentySix** | UI, tytuł w sklepach, dokumentacja, kod, komunikacja |
| **Logo / ikona / favicon** | **26** | Tylko grafika (np. ikona aplikacji); nawiązanie do 1, 20, 5 |
| **Podtytuł (sklepy, opcjonalnie)** | np. „Dart — ligi i turnieje” | Pod nazwą twentySix |
| **Formalnie (domena, prawne)** | TwentySix / twentySix | Do rejestracji znaku / domeny |
| **Repozytoria** | `twentysix-backend`, `twentysix-mobile` | Ścieżki na dysku (dawne: DartScore, Suwalska-Liga-Darta-MobileApp) |

**Zasady:**

- **Nie** nazywamy produktu „26” w tekście — to tylko **logo**, żeby uniknąć zamieszania.
- **Nie** używamy „Suwalska Liga Darta” w nowych materiałach.
- Wszędzie tam, gdzie użytkownik widzi **nazwę** aplikacji → **twentySix**.

## Otwarte pytania

*Brak otwartych pytań produktowych na ten moment. Nowe wpisy dodajemy tutaj, gdy pojawi się niejasność w trakcie implementacji.*

## Uwagi dla implementacji (stan kodu vs produkt)

Poniższe **rozbieżności** wynikają z wcześniejszej pracy bez `product.md`. **Cel:** doprowadzić kod do zgodności z dokumentem:

| Temat | Produkt | Kod dziś (skrót) |
| ----- | ------- | ---------------- |
| Podział do grup | Zapełnianie od grupy 1, równe wielkości (np. 4×6 + 3×2) | ✅ `TournamentGroupDistribution` |
| Awans z grupy | Wybór admina przy starcie | ✅ `advance_per_group`, `PlayoffService` |
| Losowanie playoff | Bez par z tej samej grupy (runda 1) | ✅ `PlayoffFirstRoundPairing` |
| Rozmiar drabinki | Zależny od `grupy × awans` | ✅ `PlayoffBracketFactory::create` (MVP do 32) |
