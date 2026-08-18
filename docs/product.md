# twentySix — wizja produktu

> **Nazwa produktu:** **twentySix** (wszędzie — UI, sklepy, dokumentacja, kod).  
> **Logo / ikona:** znak **26** (żart graczy: 1, 20 i 5) — wyłącznie warstwa wizualna, nie nazwa produktu. Szczegóły: [Marka produktu](#marka-produktu).  
> Foldery repozytoriów: **`twentysix-backend`**, **`twentysix-mobile`**.

System do prowadzenia rozgrywek darterskich: aplikacja webowa do śledzenia i zarządzania organizacjami wraz z API, aplikacja mobilna oraz **sędziowanie turnieju w przeglądarce** (ten sam kod tabletu). (Porównanie: chess.com, ale dla darta.)

## Źródło prawdy i stan kodu

**`product.md` jest źródłem prawdy** dla wszystkich decyzji produktowych i implementacyjnych.

Część kodu powstała przed `product.md` — historyczne rozbieżności zostały **domknięte w MVP v1** (lipiec 2026). Nowe funkcje muszą być zgodne z tym dokumentem.

**Status MVP v1:** tag `v1.0.0-mvp`; mapa kod ↔ wymagania: [`IMPLEMENTED_FEATURES.md`](../IMPLEMENTED_FEATURES.md) (backend), [`../twentysix-mobile/IMPLEMENTED_FEATURES.md`](../twentysix-mobile/IMPLEMENTED_FEATURES.md) (mobile). Aktywne zadania: [`NEXT_STEPS.md`](NEXT_STEPS.md).

## Dla kogo

- Organizatorzy turniejów/organizacji darterskich
- Gracze rywalizujący w turniejach oraz bezpośrednich starciach
- Kibice/zainteresowani wynikami

## Problem, który rozwiązujemy

Istnieje podobny system: [n01darts.com](https://n01darts.com/n01/). Brakuje w nim m.in.:

- stałego konta gracza i „postaci” ze statystykami,
- udziału w turniejach zawsze jako ten sam użytkownik,
- organizacji jako serii turniejów z tabelą sezonu.

W n01 gracze turniejowi są tymczasowymi nazwami bez przypisanych osiągnięć i historii poza danym turniejem.

## Wizja (efekt końcowy)

### Niezarejestrowany

- **Web:** przeglądanie publicznych organizacji, turniejów, wyników, tabel i statystyk (docelowo także pojedynczych meczów).
- **Mobile:** mecze **treningowe** bez zapisu do bazy (offline lub z internetem — wynik znika po meczu); quick game online wymaga konta i sieci.

### Zarejestrowany

- Wszystko, co gość na webie.
- Znajomi (zaproszenie + akceptacja — **mobile**; podstawowy invite/accept także na **webie** od lipca 2026).
- Własne statystyki i historia (turnieje + quick game online).
- **Mobile:** lobby quick game online, akceptacja zaproszeń (turniej, lobby, znajomi), tablet turniejowy, **mecz treningowy** (lokalny, bez zapisu).
- Docelowo: komunikator na webie, odznaczenia, link live quick game.

### Premium (docelowo)

- Wszystko, co użytkownik zarejestrowany.
- Tworzenie organizacji/turniejów (w MVP: rola **organizatora w organizacji**).

### Publiczność vs uczestnik turnieju

- Ten sam zakres podglądu turnieju.
- Zarejestrowany uczestnik: zapis w **własnej historii i statystykach** po turnieju.

### Gracz tymczasowy (gość turniejowy)

- Brak powiązania z kontem i brak merge statystyk.
- Statystyki tylko w meczu/turnieju/organizacji — nigdy w profilu.
- Dodawanie: **nazwa** wpisana przez admina.

### Cykl życia (skrót)

Rejestracja → znajomi (mobile) → quick game → twórca organizacji = organizator → turniej → zaproszenia (akceptacja mobile) → start (wybór wariantu: grupy+drabinka / SE / DE) → … → punkty sezonu.

### Rozgrywka turniejowa

Zaproszenia (web) + akceptacja (mobile) + goście → **start** możliwy **bez pełnej akceptacji** — w puli tylko **zaakceptowani + goście** → kreator startu → round-robin → tablet → playoff.

**Korekta wyniku:** ręczny poprawny wynik na webie (w tym walkower) → auto przeliczenie playoff i tabel.

### Rozgrywka poza turniejem

Quick game: FFA (każdy gra sam), max **8** graczy, format konfigurowalny (domyślnie 501 / 1 set / 2 legi), wybór **jedno urządzenie** vs **każdy na własnym** (patrz niżej). Start lobby możliwy bez pełnej akceptacji — grają tylko **zaakceptowani**.

## Tryby meczów (podsumowanie)

| Tryb                      | Zapis w bazie | Live (WebSocket) | Wymaga internetu |
| ------------------------- | ------------- | ---------------- | ---------------- |
| Quick game online (lobby) | tak           | tak              | tak (sync meczu) |
| Turniej (kod tabletu)     | tak           | tak              | tak              |
| **Trening (mobile)**      | **nie**       | **nie**          | **nie**          |

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
| Gość (web)             | Podgląd organizacji/turniejów                                               | + pojedyncze mecze     |
| Gość (mobile)          | Trening (bez konta), turniej kodem tabletu                          | bez zmian              |
| Użytkownik             | Quick game, turnieje, znajomi (akceptacja **mobile**; invite/accept także **web**); **push** zaproszeń | + komunikator |
| **Organizator**        | **Twórca organizacji = organizator**; uprawnienia w organizacji                  | premium                |
| **Współadministrator** | Pełne prawa, cała organizacja (MVP)                                        | granularne uprawnienia |
| Sędzia (tablet / web)  | Kod turnieju, wybór meczu (mobile lub `/referee`)                   | —                      |

### Kody logowania (tablety / web)

Po starcie turnieju: **jeden wspólny kod 8-znakowy** + QR (`/tablet-login/{code}`) dla wszystkich urządzeń sędziowskich; bez konta użytkownika. Ten sam kod otwiera sędziowanie w **aplikacji mobilnej** albo w **przeglądarce** (`/referee/login`, CTA na stronie QR). Regeneracja unieważnia poprzedni kod i sesje.

### Tablet — wybór meczu

Tylko `oczekujący`. **Grupy** (`groups_playoff`): kafelki grup → mecze. **Playoff / SE / DE:** płaska lista (zawodnicy + etykieta rundy / strony drabinki).

## Turniej — logika (MVP)

### Uczestnicy przy starcie

- Admin może **wystartować turniej** (i quick game), nawet gdy **nie wszyscy** zaproszeni zaakceptowali.
- Do gry wchodzą **wyłącznie** zawodnicy ze **statusem zaakceptowanym** + **goście** (nazwa od admina).
- Niezaakceptowani zaproszeni **nie są brani pod uwagę** (nie trafiają do losowania grup / drabinki).

### Minimum zawodników

| Kontekst   | Minimum | Kogo liczymy                          |
| ---------- | ------- | ------------------------------------- |
| Turniej    | **4**   | zaakceptowani + goście (łącznie)      |
| Quick game | **2**   | zaakceptowani zaproszeni do lobby     |

Bez spełnienia minimum start jest **zablokowany** (UI + walidacja API).

### Warianty turnieju (format)

Wybór **tylko przy starcie** (kreator) — po wystartowaniu bez zmiany typu.

| Identyfikator (kod) | UI (skrót) | Opis |
| --------------------- | ---------- | ---- |
| `groups_playoff` | Grupy + drabinka | Obecny standard: faza grupowa → playoff |
| `single_elimination` | Single elimination | Tylko drabinka SE (bez grup) |
| `double_elimination` | Double elimination | Tylko drabinka DE (bez grup); WB + LB |

**Wspólne dla wszystkich wariantów:** min. 4 graczy; format meczu (501/legi/sety) jak dziś; tablet H2H; walkower/korekta → auto przeliczenie; punkty sezonu po zakończeniu.

**Rozmiar drabinki:** potęgi 2, **max 128**. Model slotów: **generyczny** (`round` + `index` + kierunki awansu), nie rozrost enumów `PlayoffSlot` / `WinnerDestinationSlot`.

**Kolejność wdrożenia:** najpierw SE, potem DE. Reguły i model: [`design_tournament_formats_se_de.md`](design_tournament_formats_se_de.md).

#### `groups_playoff` (bez zmian reguł względem MVP v1)

- Faza grupowa + playoff; playoff **bez bye** (pełna drabinka z awansujących).
- Limit awansujących do drabinki: **max 128** (wcześniej MVP: 32 — podnosimy wraz z generycznym silnikiem).

#### `single_elimination`

- Bez fazy grupowej → od razu status pucharowy; mecze R1 (i gotowe bye) na tablecie.
- **Bye dozwolone:** `bracketSize = najbliższa potęga 2 ≥ N` (np. 6 graczy → drabinka 8, 2 bye).
- Pary R1 + rozmieszczenie bye: **losowo** (bez seedingu z rankingu w tym zakresie).
- **Mecz o 3. miejsce: tak** (przegrani półfinałów).
- Miejsca: 1/2 z finału, 3/4 z meczu o 3., dalej z rundy odpadnięcia (ex aequo w tej samej rundzie).

#### `double_elimination`

- Bez fazy grupowej → od razu drabinka; **bye** jak w SE.
- Pary R1 WB + bye: **losowo**.
- Przegrana w WB = spadek do LB (pierwsza porażka); **druga porażka** = eliminacja i miejsce.
- **Bez meczu o 3. miejsce.**
- **Grand final:** wybór admina przy starcie — **jeden mecz** albo **reset** (jeśli LB wygra GF1 → drugi mecz GF2). Gdyby wybór okazał się zbyt kosztowny w implementacji: domyślnie tylko **reset**.
- UI web: **dwie oddzielne drabinki** (wygranych / przegranych) + blok Grand Final — nie jedna zlepiona wizualizacja.
- Miejsca z rundy **drugiej** porażki (przykład 8 graczy):

| Miejsce | Kto |
| ------- | --- |
| 1 | zwycięzca Grand Final (ew. GF2) |
| 2 | przegrany Grand Final (ew. GF2) |
| 3 | przegrany **LB Final** (ostatni mecz LB przed GF) |
| 4 | przegrany **półfinału LB** |
| 5–6… | wcześniejsze rundy LB (ex aequo w tej samej rundzie) |

### Start turnieju (kreator na webie)

0. **Typ turnieju** — `groups_playoff` / `single_elimination` / `double_elimination` (oraz dla DE: tryb Grand Final, gdy obie opcje są dostępne).

Dla **`groups_playoff`:**

1. **Liczba grup** — dowolna liczba całkowita od **2** do maksimum wynikającego ze składu (co najmniej **3 zawodników w każdej grupie**). Np. 37 graczy → dozwolone 2–12 grup (w tym 6, 7, …).
2. **Etap drabinki** — wybór etapu, od którego zaczyna się faza pucharowa, z opisem liczby awansujących (np. „1/8 finału — 16 graczy awansujących”). Dozwolone tylko potęgi 2: 4, 8, 16, 32, 64, 128.
   - Maksymalnie **128 awansujących** do drabinki.
   - Awansujących musi być **≥ liczba grup** (minimum 1 z każdej grupy) i **≤ liczba zawodników**.
   - Miejsca awansujące rozkładane **proporcjonalnie** do wielkości grup; nadwyżka trafia do większych (wcześniejszych) grup. Przy starcie zapisywany jest rozkład per grupa (`group_advances`).
3. **Kod logowania tabletu** — jeden wspólny kod 8-znakowy + QR (niezależnie od grup/drabinki).
4. Losowy podział puli zawodników do grup (reguła wielkości — patrz niżej).
5. Round-robin w każdej grupie.
6. **Podgląd w kreatorze:** dla wybranej liczby grup i etapu drabinki administrator widzi „Grupa N: X graczy → Y awansujących”.

Dla **`single_elimination` / `double_elimination`:**

1. Bez liczby grup / awansu — `bracketSize = nextPowerOfTwo(N)`; podgląd „N graczy → drabinka X, Y wolnych losów”.
2. Jeden kod logowania tabletu + QR; format meczów per etap (bez etapu `GROUP`).
3. Start → od razu faza pucharowa (mecze R1 widoczne na tablecie).

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

### Grupy i playoff (`groups_playoff`)

- Round-robin: każdy z każdym w grupie.
- **Playoff startuje automatycznie** po rozegraniu **ostatniego meczu grupowego** (status turnieju → playoff).
- Drabinka playoff po grupach: pełna, **bez wolnych losów**; auto przeliczenie po korekcie wyniku na webie.
- Wariantach SE/DE bye są **dozwolone** (patrz sekcja „Warianty turnieju”).

### Losowanie par playoff (pierwsza runda)

- Pary w **pierwszej rundzie** playoff dobierane **losowo** z puli awansujących.
- **Ograniczenie:** w pierwszym meczu playoff **nie mogą** trafić na siebie **dwaj zawodnicy z tej samej grupy** (unikamy natychmiastowego re-matchu po fazie grupowej).
- Algorytm: losowanie z **ponowieniem / zamianą par**, aż układ spełnia warunek (lub deterministyczne tasowanie z walidacją — implementacja dowolna, efekt ten sam).
- Kolejne rundy playoff: standardowe wynikanie z drabinki (zwycięzca → następna runda).

### Walkower i korekta wyniku

- Admin na webie wchodzi w mecz i wpisuje **poprawny wynik** (nie osobny flow „cofnij”).
- **Walkower:** wynik **do zera** — przy domyślnym formacie (1 set, 2 legi) = **2:0** w legach; przy wielu setach = wynik w **setach** (np. 2:0), zgodnie z `legsToWinSet` / `setsToWinMatch` meczu.
- Po zapisie: **automatyczne przeliczenie** dalszych rund playoff i tabel.

### Achievementy

Działają poprawnie w MVP w meczu turniejowym (180, 170+, QF, HF itd.).

### Zaproszenia do turnieju

- Wysyłka: admin na **webie** — **na stronie startu turnieju** (bez osobnej podstrony): wyszukiwarka + lista zaproszonych + masowe zaproszenia ze składu organizacji.
- Akceptacja / wycofanie udziału: **mobile**.
- Goście (nazwa od admina): edycja puli gości na ekranach sezonu/organizacji; na stronie startu admin **dodaje gościa do turnieju** z listy powiązanych.
- **Zgłoszenia przez QR** (✅): gracz skanuje QR na starcie turnieju → admin Dołącz/Odrzuć.

#### Stały skład organizacji (`relatedUsers`)

- Organizacja (i opcjonalnie sezon) utrzymuje listę **powiązanych użytkowników** — **stali bywalcy**, którzy regularnie grają w turniejach tej organizacji.
- Lista **nie wpisuje** nikogo automatycznie do turnieju — służy do **szybkiego masowego wysyłania zaproszeń** (zaznaczenie wielu osób → wyślij zaproszenia).
- Na stronie startu turnieju skład do masowego invite = **suma `organization.relatedUsers` + `season.relatedUsers` bez duplikatów** (jak dziś `getRelatedPlayers`, ale tylko użytkownicy z kontem — bez gości).
- Zarządzanie składem (dodawanie/usuwanie osób z listy organizacji/sezonu) pozostaje na dotychczasowych ekranach `relatedUsers`.

#### Strona startu turnieju (web) — układ B

1. **Uczestnicy turnieju** (na górze; na desktopie sticky z ograniczoną wysokością listy i przewijaniem) — skład startowy; licznik min. graczy; usuwanie (×).
2. **Dodaj uczestników** — jedna sekcja z zakładkami:
   - **Zarejestrowani:** wyszukiwarka, zaproszenia w toku (bez accepted), stały skład organizacji (masowy invite).
   - **Goście:** powiązani goście organizacji/sezonu → „Dodaj” do turnieju.
3. **Start turnieju** — grupy / awans / tablety (wszyscy z segmentu uczestników).

#### Statusy zaproszenia turniejowego

| Status | Znaczenie | Kto ustawia |
| ------ | --------- | ----------- |
| `pending` | Oczekuje na odpowiedź gracza | — (po wysłaniu) |
| `accepted` | Gracz potwierdził udział | gracz (mobile) |
| `rejected` | Gracz odrzucił | gracz (mobile) |
| `cancelled` | Admin anulował zaproszenie **pending** | admin (web) |
| `withdrawn` | Gracz wycofał udział **po akceptacji** | gracz (mobile) |
| `removed` | Admin usunął gracza **po akceptacji** | admin (web) |

- Do puli startowej wchodzą wyłącznie uczestnicy z segmentu **„Uczestnicy turnieju”**: zaproszenia **`accepted`** + goście **jawnie dodani do turnieju** (`tournament_guest_participants`).
- Admin może **anulować** zaproszenie w statusie `pending`.
- Admin może **wyrzucić** gracza ze statusem `accepted` → `removed` — **tylko przed startem turnieju** (MVP).
- Gracz może **wycofać udział** ze statusem `accepted` → `withdrawn` — **tylko przed startem turnieju** (MVP).
- **Ponowne zaproszenie:** admin może wysłać zaproszenie ponownie po `rejected`, `cancelled`, `withdrawn`, `removed` (nowy rekord lub reaktywacja — brak duplikatu przy `pending` / `accepted`).

**Poza MVP (docelowo):** po starcie turnieju admin może wyrzucić / gracz wycofać udział — wszystkie mecze takiego zawodnika stają się **walkowerami** (auto 2:0).

#### API (mobile)

- `GET /api/tournaments/invitations/received` — oczekujące + zaakceptowane (do ekranu akceptacji / wycofania).
- `POST /api/tournaments/invitations/{id}/accept`
- `POST /api/tournaments/invitations/{id}/reject`
- `POST /api/tournaments/invitations/{id}/withdraw` — tylko własne, status `accepted`, turniej **nie wystartował**

#### Mobile — ekran zaproszeń

- **Jeden ekran** z zakładkami: **Turniej** | **Pojedynek** (quick game / lobby) | **Znajomi**.
- Pull-to-refresh na liście. **Push** przy nowym zaproszeniu (znajomi / turniej / lobby) — ✅.

## Organizacja i punktacja (MVP)

- Organizacja = seria turniejów + tabela sezonu + tabele per turniej.
- **Point scheme** narzucany przez system według **liczby graczy w turnieju** (schematy w kodzie).
- Organizacja może mieć **wiele lig** (piramid) — osobny byt, patrz niżej.

## Liga (piramida)

Liga **nie** jest synonimem organizacji. To piramida szczebli indywidualnych wewnątrz organizacji (jak Ekstraklasa / 1. liga), z awansami, spadkami i barażami.

```
Organizacja
├── Sezon turniejowy     ← Season + Tournament (jak dotychczas)
└── Liga (wiele na organizację)
      ├── Szczebel (pozycja 0 = najwyższa)
      ├── Trwały skład piramidy
      └── Sezony ligowe (zamrożone zdjęcie + mecze + tabela)
```

- Tylko **indywidualna**; format **X01** per szczebel (501 / legi / sety), zamrażany na start sezonu.
- Sezon ligowy i sezon turniejowy to **osobne byty**.
- Kalendarz przy starcie sezonu ligowego:
  - **Kolejki:** albo **długość kolejki + data startu** (koniec sezonu wyliczany ze składu), albo **start i koniec sezonu** (długość kolejki = równy podział tego okresu).
  - albo **pula meczów z jednym deadline’em**.
- Mecze: pełny round-robin, 1 albo 2 spotkania każdy z każdym (rewanż bez przewagi gospodarza).
- Gra **na miejscu, sędzia, jedno urządzenie** (jak turniej). Wpis wyniku / walkower na **webie**; scoring tabletem — później.

### Cykl życia

1. **Poza sezonem** — admin rusza strukturę, składy, dopisuje/wypisuje ludzi na szczebel.
2. **Start sezonu** — zdjęcie składu i reguł, generacja meczów, zamrożenie piramidy.
3. **W sezonie** — bez nowych szczebli i bez przesuwania ludzi. Wolno: rezygnacja; przy zaległym meczu: WO jednostronny / obustronny / przedłużenie. Admin może **anulować sezon** (hasło + wpisanie nazwy) — wraca skład sprzed startu, mecze giną.
4. **Koniec** — tabele → auto awans/spadek/baraż → łatanie dziur z dołu → piramida zaktualizowana.

### Tabela i baraże

Kolejność: **zwycięstwa → różnica legów (jednostek meczu) → bilans bezpośredni → dogrywka → losowanie**.

- 2 osoby w remisie na linii awansu/spadku: jeden mecz (deathmatch).
- 3–4: mini-drabinka SE (bye jeśli trzeba), mecz o 3. miejsce przy 4 osobach.
- Losowanie tylko gdy sportowo się nie da (albo remis w środku tabeli, bez wpływu na awans/spadek).

Admin ustawia per szczebel (na niższym): ile miejsc awansuje **bezpośrednio**, ile idzie do **barażu**. `0` baraży = czysty awans/spadek. Parowanie: najlepszy barażowicz z dołu vs najsłabszy zagrożony z góry. Format **wyższego** szczebla, jeden mecz.

### Rezygnacja

Nie mylić z WO pojedynczego meczu. Rezygnacja: **wszystkie** mecze tej osoby anulowane (także zagrane), sezon jakby jej nie było, wypada z piramidy. W trakcie sezonu nikt z niższej ligi nie wskakuje. Po sezonie: najpierw sportowe awanse/spadki/baraże, potem dziury łata kolejka z dołu — spadków się nie anuluje, żeby załatać dziurę.

WO obustronny przy **pojedynczym** meczu: obaj porażka, 0:0. WO jednostronny: walkower w formacie szczebla (np. BO3 → 2:0).

## Znajomi i quick game

### Znajomi (MVP)

- **Mobile:** zaproszenie + akceptacja (główny flow akceptacji zaproszeń turniejowych i lobby).
- **Web (od lipca 2026):** invite → accept na profilu gracza i w panelu bocznym — **bez** komunikatora.
- **Docelowo:** pełny komunikator.
- **Push** przy zaproszeniach (znajomi / turniej / quick game): ✅.

### Quick game online (MVP)

**Wymaga:** konto zalogowane, internet, lobby ze znajomymi.

- Lobby zakłada zalogowany użytkownik (host).
- **Dołączenie wyłącznie przez zaproszenie** — host zaprasza znajomego z listy znajomych; zaproszony **akceptuje** na mobile i wtedy dołącza do lobby (`POST …/lobby/{id}/join`).
- **Brak kodów lobby** w quick game — kody 6-znakowe dotyczą **tylko turniejów** (logowanie tabletu / sędziowanie). Quick game nie generuje ani nie udostępnia kodu do dołączenia.
- Tylko **znajomi**; max **8** zawodników.
- **FFA** — każdy gra sam (1v1, 1v1v1v1…; nie drużyny 2v2).
- Format konfigurowalny — patrz sekcja **Format gry** (domyślnie **501 · 1 set · 2 legi**).
- **Zwycięzca meczu:** zawodnik, który **pierwszy** osiągnie wymaganą liczbę **setów** (przy 1 secie = legów do wygranej meczu).
- **Kolejność zawodników** ustawiana w lobby (np. A, B, C, D).
- **Kolejność rzutów w legu:** zaczynający leg → następny w kolejce → … (cyklicznie).
- **Kto zaczyna kolejny leg:** zawsze **następny zawodnik po tym, który zaczynał poprzedni leg** — **niezależnie od tego, kto wygrał leg**.
  - Leg 1 zaczyna **A** → kolejność rzutów: **A → B → C → D** (i dalej cyklicznie do końca lega).
  - Leg 2 zaczyna **B** (następny po A) → kolejność: **B → C → D → A** — nawet jeśli leg 1 wygrał np. B lub D.
  - Leg 3 zaczyna **C** (następny po B) → kolejność: **C → D → A → B** — nawet jeśli leg 2 wygrał np. D.
  - Leg 4 (jeśli potrzebny) zaczyna **D** → kolejność: **D → A → B → C**.
- Start bez pełnej akceptacji — grają tylko **zaakceptowani** zaproszeni.
- **Minimum 2** zaakceptowanych zawodników do startu.
- Wyniki w statystykach gracza (zapis w bazie po zakończeniu meczu FFA).

### Mecz treningowy (mobile, MVP)

**Wymaga:** tylko aplikacja mobilna — **bez konta**, **bez internetu**, **bez zapisu** w bazie po meczu.

Przeznaczenie: grupa znajomych przy tarczy (np. w klubie bez Wi‑Fi) albo szybka gra „na miejscu”, gdy nie chcemy zapisywać wyniku w statystykach — **nawet jeśli internet jest dostępny**.

| Aspekt | Trening | Quick game online |
| ------ | ------- | ----------------- |
| Wejście w app | **Trening** (ekran konfiguracji) | **Quick game** → lobby |
| Konto | nie wymagane | wymagane |
| Internet | nie wymagany | wymagany (sync) |
| Zapis wyniku | **nie** — dane znikają po zamknięciu meczu | tak (`quick_games`, statystyki) |
| Gracze | **1–8**, **imiona wpisane lokalnie** (bez kont); **solo dozwolone** we wszystkich trybach | 2–8, tylko **znajomi** z kont |
| Format | Konfigurowalny (host); domyślnie 501 / 1 set / 2 legi | j.w. |
| Tryb urządzeń | **`one_device`** — jeden telefon wpisuje wszystkich | `one_device` lub `each_own` + sync |
| Reguły FFA | ta sama rotacja openera i kolejność tur co online | j.w. |

**Implementacja:** scoring wyłącznie w aplikacji (`GameScoringScreen`, lokalne reducery). Backend **nie uczestniczy** w treningu. Wyniki quick game online zapisuje `QuickGameFfaScoringService` → `quick_games`; achievementy opcjonalnie przez `POST /api/quick-game/update`.

**Poza MVP treningu:** sync wielu telefonów bez konta, zapis opcjonalny.

### FFA 3–8 graczy — oba tryby urządzeń (quick game online, MVP)

Quick game **2–8 graczy** obsługuje **oba** tryby wybrane w lobby:

| Liczba graczy | `one_device` | `each_own` |
| ------------- | ------------ | ---------- |
| **2** | ✅ host wpisuje obu | ✅ FFA sync API + WS |
| **3–8** | ✅ **wymagane** — np. pięciu kumpli przy jednej tarczy, jeden telefon/tablet wpisuje wszystkich | ✅ **wymagane** — ci sami gracze **zdalnie**, każdy na swoim telefonie, wpisuje **tylko w swojej turze**; wspólny stan przez API + WebSocket |

**Przykłady (product):**

- **Na miejscu:** 5 znajomych w klubie → lobby 5 osób, tryb **jedno urządzenie**, host wpisuje rzuty wszystkich na jednym tablecie.
- **Zdalnie:** tych samych 5 znajomych, każdy w domu → lobby 5 osób, tryb **każdy na swoim**, ten sam widok meczu na każdym telefonie, synchronizacja tur i rotacji openera lega.

Te same reguły FFA (kolejność z lobby, rotacja openera, format meczu, wynik w statystykach) obowiązują w **obu** trybach. Różni się tylko **kto wpisuje punkty** i **mechanizm synchronizacji** (brak sync między telefonami vs API/WS).

### Quick game — tryb urządzeń (wybór w lobby)

Już istniejący wybór w lobby mobilnym:

| Tryb | Zachowanie |
| ---- | ---------- |
| **Jedno urządzenie** | Jedna osoba (zwykle **host**) na jednym telefonie/tablecie wpisuje rzuty **wszystkich** zawodników (jak sędziowanie). Dotyczy **2–8** graczy FFA. Pozostali widzą postęp meczu (lobby / ekran meczu), ale **nie wpisują** punktów. |
| **Każdy na własnym urządzeniu** | Ten sam widok meczu na każdym telefonie, **synchronizacja przez API** + WebSocket. Zawodnik wpisuje rzuty **tylko w swojej kolejce** — czeka na turę. Dotyczy **2–8** graczy (jeden silnik FFA). |

- Turniej na tablecie = zawsze model **jednego urządzenia** (head-to-head).
- **Krykiet** — poza MVP (kod w toku, wrócimy później).

### Quick game (docelowo)

- Dowolny zalogowany w lobby; **krykiet** i inne formaty gry (`gameType`).

## Format gry (konfigurowalny)

**Reguła checkoutu MVP:** double out (bez zmiany).

**Kontrakt `MatchFormat`** (backend + mobile):

| Pole | Znaczenie | Domyślnie |
| ---- | --------- | --------- |
| `startingScore` | Punkty startowe lega (X01) | **501** |
| `legsToWinSet` | Legi do wygrania **seta** („pierwszy do N”) | **2** |
| `setsToWinMatch` | Sety do wygrania **meczu** | **1** |
| `gameType` | `x01` (501/301…), `cricket`, `bob27`, `atc`, `catch40`, `cricket56` | `x01` |
| `outRule` | `double_out` (X01) | `double_out` |
| `bob27Mode` | `hard` / `easy` (tylko Bob's 27) | `hard` |
| `bob27Bull` | `with` / `without` (tylko Bob's 27; czy gra się inner bull po D20) | `with` |

**Preset domyślny:** 501 · 1 set · 2 legi (= dotychczasowe BO3).

**Punkty startowe (picker):** 101, 201, 301, 401, 501, 601, 701, 801, 901, 1001.

**Zwycięzca meczu:** pierwszy gracz z `setsWon >= setsToWinMatch`. Przy `setsToWinMatch === 1` w UI można pokazać skrót „501 · do N legów”.

### Turniej

- Admin ustawia format **przy starcie turnieju** (web), **osobno per etap** (`GROUP`, `SIXTEEN`, …, `SEMI`, **`THIRD`**, `FINAL`).
- Zapis w `tournament_match_formats`; przy tworzeniu każdego meczu **snapshot** na rekordzie `games` / `playoff_games`.
- **Tablet nie konfiguruje formatu** — po locku meczu scoring API zwraca `meta.matchFormat` z rekordu meczu.
- Edycja formatu turnieju **po starcie** — zabroniona (mecze mają snapshot).

### Quick game online + trening (mobile)

- Host wybiera **sety**, **legi/set**, **punkty** przed startem (quick: w lobby; trening: ekran konfiguracji).
- **Ostatnio używane ustawienia** — osobno w AsyncStorage: trening vs quick game (nie przenoszą się między trybami). Pierwsze uruchomienie → preset domyślny.
- Backend quick game zapisuje format w lobby / sesji FFA (sync online).

### Walkower / korekta (web)

- **`setsToWinMatch === 1`:** wynik w legach (np. 2:0 przy „do 2 legów”).
- **`setsToWinMatch > 1`:** wynik w setach (np. 2:0); szczegóły legów w secie opcjonalnie w UI korekty.

Konfigurowalny format X01 (fazy 1–4 + presety organizacji): ✅. Cricket: trening + quick (bez turnieju) — ✅. **Bob's 27**, **Around the Clock**, **Catch 40** i **Cricket 60:** trening + quick (bez turnieju) — ✅; szczegóły poniżej.

### Format gry — podsumowanie kontekstów

| Kontekst            | Gra                 | Format |
| ------------------- | ------------------- | ------ |
| Turniej (tablet)    | 501 head-to-head    | Z rekordu meczu (admin ustawił przy starcie turnieju) |
| Quick game online   | X01 / Cricket / Bob's 27 / Around the Clock / Catch 40 / Cricket 60 FFA | Host w lobby; sync w sesji FFA |
| Trening (mobile)    | X01 / Cricket / Bob's 27 / Around the Clock / Catch 40 / Cricket 60 | Host lokalnie; **bez zapisu** w bazie |
| Krykiet / Bob's 27 / Around the Clock / Catch 40 / Cricket 60 w turnieju | —            | poza zakresem |

Ten sam silnik liczenia (leg → set → mecz) i kontrakt API (`meta.matchFormat`, `setsWon`, `legsWonInSet`).

### Bob's 27 (trening + quick game)

Trening dubli Boba Andersona. **Nie ma w turnieju.**

- Start: **27 punktów**. Kolejność celów: **D1 → D20**, opcjonalnie **inner bull (50)** — wybór w lobby jak Hard/Easy. Domyślnie **z bullem**. Outer bull **nie** liczy się.
- Zawsze **3 lotki** na cel, potem następny gracz / następny dubl. Wpisywanie: **ile lotek trafiło celu (0–3)** — jedno kliknięcie na wizytę. Trafienie dodaje wartość dubla **za każdą lotkę**; trzy pudła odejmują **jedną** wartość (D6: hit +12, trzy pudła −12).
- **Hard** (domyślnie): wynik ≤ 0 = wypadasz z lega. Ostatni ocalały wygrywa lega; po ostatnim celu wygrywa najwyższy wynik (remis = runda od nowa).
- **Easy:** gra trwa przy ujemnym wyniku; po ostatnim celu wygrywa najwyższy wynik.
- **Z bullem** (domyślnie): ostatni cel = inner bull. **Bez bulla:** ostatni cel = D20.
- Perfekcyjna gra: **1437** (z bullem) / **1287** (bez bulla).
- Quick: FFA 2–8, oba tryby urządzeń, API `/ffa/bob27/darts`. Trening: także **solo**.

### Around the Clock (trening + quick game)

Wyścig po tarczy. **Nie ma w turnieju.**

- Kolejność celów: **1 → 2 → … → 20 → bull** (outer albo inner — oba liczą się).
- Każdy gracz ma **własny** aktualny numer. Lotka zalicza tylko ten numer (dowolny segment S/D/T); **bez przeskoków**.
- 3 lotki na wizytę; w jednym podejściu można iść dalej, jeśli kolejne lotki trafiają kolejne numery. Wpisywanie: **ile kolejnych numerów (0–3)** — jedno kliknięcie.
- Trafienie ostatniego celu **od razu zamyka lega** (nawet na 1. lotce).
- Quick: FFA 2–8, oba tryby urządzeń, API `/ffa/atc/visits`. Trening: **solo** albo 2–8.

### Catch 40 (trening + quick game)

Trening checkoutów Johna Parta. **Nie ma w turnieju.**

- Kolejno outy **61 → 100** (40 checkoutów). Każdy gracz ma **własny** numer.
- Max **6 lotek** na out (2 wizyty). Double out jak w X01 (bust / pozostałe 1).
- Punkty: **2 lotki = 3**, **3 lotki = 2**, **4–6 lotek = 1**, pudło w 6 lotek = 0.
- Wyjątek: **99 w 3 lotki = 3**. Max **120**.
- Leg zamyka się dopiero gdy **wszyscy** skończą 40 outów — wygrywa najwyższy wynik (remis = runda od nowa).
- Klawiatura **X01** (suma trzech / każdy rzut osobno).
- Quick: FFA 2–8, oba tryby urządzeń, API `/ffa/catch40/visits`. Trening: także **solo**.

### Cricket 60 (trening + quick game)

Siedem rund po tarczy cricketowej. **Nie ma w turnieju.** Nazwa od wyniku perfect **60** (żeby nie mylić ze zwykłym Cricket). W kodzie `gameType`: `cricket56`.

- Rundy: **15 → 16 → 17 → 18 → 19 → 20 → bull**. Wszyscy rzucają w ten sam cel w danej rundzie, potem następna.
- 3 lotki na rundę. Punkty tylko za aktualny cel: **S=1, D=2, T=3**. Bull: **outer=1, inner=2**. Inny sektor = 0.
- Po 7. rundzie wygrywa najwyższy wynik (remis = runda od nowa). Max **60** (6×9 + 3×2).
- Wpisywanie: **0 / S / D / T** per lotka (T wyłączone na bullu); po 3. lotce wizyta idzie dalej.
- Quick: FFA 2–8, oba tryby urządzeń, API `/ffa/cricket56/visits`. Trening: także **solo**.

### Statystyki w trakcie meczu (mobile — licznik i zakładka „Statystyki”)

Wspólne dla **quick game online**, **treningu** i (tam gdzie dotyczy) **turnieju na tablecie**. Średnie w UI zawsze w formacie **xx.xx** (np. `9.00`).

| Pole / etykieta UI | Znaczenie | Kto ma wartość |
| ------------------ | --------- | -------------- |
| **ms** — średnia meczowa (`matchAverage` / `gameAverage`) | Średnia 3-dartowa ze **wszystkich** rzutów gracza w meczu (wygrane i przegrane legi + bieżący leg). | Zawsze obaj / wszyscy gracze, gdy mają co najmniej jedną wizytę. |
| **ls** — średnia legowa bieżąca (`currentLegAverage` / `legAverage`) | Średnia 3-dartowa w **aktualnie trwającym** legu. | Zawsze obaj / wszyscy, gdy mają rzuty w bieżącym legu. |
| **Najlepszy leg** (sekcja *Średnia*) — `max(legsAverages)` | Najwyższa średnia legowa spośród **zakończonych** legów, w których gracz brał udział — **niezależnie od wyniku lega** (wygrany lub przegrany). | Zawsze liczona dla każdego gracza, który rozegrał co najmniej jeden zakończony leg. |
| **Najlepszy leg** (sekcja *Osiągi*) — `min(dartsPerLeg)` | **Najmniejsza** liczba lotek potrzebnych graczowi do **zamknięcia lega**, który **wygrał**. To liczba lotek zwycięzcy lega, nie przegranego. | Tylko gracze, którzy **wygrali co najmniej jeden** zakończony leg; inaczej `-`. |
| `legsAverages[]` | Średnia legowa per zakończony leg — **każdy** rozegrany leg (wygrany i przegrany). | Backend / reducer — pod „najlepszą średnią legową”. |
| `dartsPerLeg[]` | Liczba lotek gracza w legu, który **wygrał** (checkout). | Tylko wygrane legi — pod „najlepszy leg” w osiągach. |
| Osiągi 60+, 80+, 100+, 140+, 180 | Liczba wizyt w przedziałach punktowych w meczu. | Wszystkie wizyty gracza (`legByLegScores` + bieżący leg). |

**Implementacja (skrót):** quick game FFA — `QuickGameFfaStateBuilder` + wspólny `VisitRecorder` (backend); mobile: `applyGameScoringState` + `useGameScoring` → reducer `SYNC_FROM_SERVER`. Turniej H2H — ten sam kontrakt API (`format`, `turn`, `revision`, `meta`).

## Reguły meczu (MVP)

- **Web (twentySix):** organizacje/turnieje, start turnieju, zaproszenia do turnieju (wysyłka), korekta wyników, live, publiczny podgląd, **znajomi** (invite/accept — bez komunikatora).
- **API:** walidacja grup×awans, podział do grup, statusy meczów, quick game (oba tryby urządzeń), zaproszenia, achievementy, point schemes.
- **Mobile:** tablet, quick game online, **trening (lokalny)**, znajomi (MVP), akceptacja zaproszeń (turniej, lobby, znajomi).

### Publiczny podgląd live (web)

Podglądy live meczu (`/games/{type}/{id}/live` — H2H grupowy/playoff/quick) oraz sesji FFA (`/quick-game/lobby/{lobbyId}/live`) są **celowo publiczne** — bez `middleware('auth')`, dostępne po numerycznym id jako link do udostępnienia (ten sam wzorzec co istniejący `games.show`). Auth chroni **granie/wpisywanie wyniku**, nie **oglądanie**.

## MVP (wersja 1 — musi działać)

### Web

- Twórca organizacji = organizator; współadmin per organizacja
- Turniej: zaproszenia (`tournament_invitations`), goście, start (grupy + walidowany awans + kody)
- Start z podzbiorem zaakceptowanych zawodników
- Korekta wyniku / walkower (zgodnie z formatem meczu); live WebSocket

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
- Quick game online: FFA **2–8**, **oba tryby urządzeń** (`one_device` + `each_own`), format konfigurowalny (domyślnie 501/1/2)
- **Trening:** FFA 2–8, `one_device`, format konfigurowalny, bez konta i bez zapisu (działa offline)
- Tablet turniejowy
- Ekran startowy: **Quick game online** vs **Trening**

### Web (dodatkowo poza pierwotnym scope v1)

- Znajomi na webie (invite → accept) — **zrealizowane lipiec 2026**

## Poza MVP (świadomie później)

- Krykiet
- Komunikator, odznaczenia, stream, premium
- **Awatary graczy** — upload zdjęcia profilowego (web + mobile); przy `Player`; limity pliku, fallback inicjałów; crop / CDN później. Backlog: [`NEXT_STEPS.md`](NEXT_STEPS.md).
- **Aktualizacja APK w apce** — komunikat przy starcie i instalacja nowej wersji (Android self-update / później Play In-App Updates; nie OTA JS). Backlog: [`NEXT_STEPS.md`](NEXT_STEPS.md). **Nie teraz.**
- Granularne uprawnienia współadmina
- Quick game z dowolnym zalogowanym
- ~~**Drabinka playoff > 32**~~ — decyzja sierpień 2026: generyczny model slotów + **max 128** w ramach wariantów SE/DE ([`design_tournament_formats_se_de.md`](design_tournament_formats_se_de.md)); `groups_playoff` na tym samym silniku.
- **Warianty SE / DE** — ✅ w kodzie; reguły: [`design_tournament_formats_se_de.md`](design_tournament_formats_se_de.md).

## Czego nie robimy (na razie)

- Krykiet w MVP
- Tryby drużynowe 2v2 w quick game
- Wolne losy w drabince **po fazie grupowej** (`groups_playoff`) — bye tylko w czystym SE/DE
- Seeding drabinki z rankingu sezonowego (MVP wariantów: tylko losowe R1)
- Równoległe wpisywanie rzutów w multi-device (tylko kolejno)

## Kryterium „MVP jest gotowe”

1. Znajomi + zaproszenia turniej/lobby — akceptacja na mobile.
2. Start turnieju/quick game bez pełnej akceptacji; gra tylko zaakceptowani (+ goście w turnieju).
3. Turniej min. 4 zawodników (łącznie z gośćmi); grupy, tie-breakery, round-robin; playoff bez bye; losowanie rundy 1 bez par z jednej grupy.
4. Tablet + live web; achievementy; auto start playoff.
5. Quick game min. 2; do 8 graczy FFA; format konfigurowalny (domyślnie do 2 legów w 1 secie); rotacja startu legów; oba tryby urządzeń; statystyki.
6. Walkower/korekta na webie (zgodnie z formatem meczu) → auto przeliczenie; point scheme z liczby graczy.
7. Offline/solo bez zapisu; gość ogląda organizacje/turnieje.

## Marka produktu

| Kontekst | Nazwa | Uwagi |
| -------- | ----- | ----- |
| **Produkt (wszędzie)** | **twentySix** | UI, tytuł w sklepach, dokumentacja, kod, komunikacja |
| **Logo / ikona / favicon** | **26** | Tylko grafika (np. ikona aplikacji); nawiązanie do 1, 20, 5 |
| **Podtytuł (sklepy, opcjonalnie)** | np. „Dart — organizacje i turnieje” | Pod nazwą twentySix |
| **Formalnie (domena, prawne)** | TwentySix / twentySix | Do rejestracji znaku / domeny |
| **Repozytoria** | `twentysix-backend`, `twentysix-mobile` | Ścieżki na dysku (dawne: DartScore, Suwalska-Liga-Darta-MobileApp) |

**Zasady:**

- **Nie** nazywamy produktu „26” w tekście — to tylko **logo**, żeby uniknąć zamieszania.
- **Nie** używamy „Suwalska Liga Darta” w nowych materiałach.
- Wszędzie tam, gdzie użytkownik widzi **nazwę** aplikacji → **twentySix**.

## Otwarte pytania

*Brak otwartych pytań produktowych — reguły wariantów turnieju (SE/DE) zamknięte sierpień 2026; szczegóły wdrożenia: [`design_tournament_formats_se_de.md`](design_tournament_formats_se_de.md).*

## Zgodność kodu z produktem (lipiec 2026)

Historyczne rozbieżności z czasów przed `product.md` — **domknięte w MVP v1**. Tabela referencyjna (nie lista TODO):

| Temat | Wymaganie produktu | Kod |
| ----- | ------------------ | --- |
| Podział do grup | Zapełnianie od grupy 1, równe wielkości | ✅ `TournamentGroupDistribution` |
| Awans z grupy | Etap drabinki + rozkład per grupa | ✅ `playoff_bracket_size`, `group_advances`, `PlayoffService` |
| Losowanie playoff | Bez par z tej samej grupy (runda 1) | ✅ `PlayoffFirstRoundPairing` |
| Rozmiar drabinki | Wybór etapu (`playoff_bracket_size`; **docelowo max 128**, dziś w kodzie jeszcze 32) | ⚠️ `PlayoffBracketFactory::create` (enumy) → generyczny silnik w planie SE/DE |
| Warianty SE / DE | Typ przy starcie; bye; miejsca; GF | ✅ [`design_tournament_formats_se_de.md`](design_tournament_formats_se_de.md) |
| Zaproszenia turniejowe | Encja per turniej; web (start turnieju); akceptacja mobile; `relatedUsers` = tylko masowy invite | ✅ `TournamentInvitation`, API, `InvitationsScreen` |
| Dołączenie do quick game | Tylko zaproszenie → akceptacja; brak kodów lobby | ✅ |
| FFA 2–8 oba tryby urządzeń | `one_device` i `each_own` | ✅ unified FFA |
| Rotacja openera lega | `(opener + 1) % N` | ✅ |
