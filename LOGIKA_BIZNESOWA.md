# Logika biznesowa – twentySix (backend + aplikacja mobilna)

**Wspólny dokument** dla obu części produktu **twentySix**:

- **Backend + web** (`twentysix-backend`) – Laravel, panel, API, WebSocket
- **Mobile** (`twentysix-mobile`) – React Native / Expo

Stanowi **jedną wspólną wersję** logiki biznesowej przy rozwoju obu projektów.

---

## Dwie główne funkcjonalności (aplikacja mobilna)

Aplikacja mobilna służy na ten moment do **dwóch odrębnych rzeczy**:

| # | Funkcjonalność | Opis |
|---|----------------|------|
| 1 | **Szybki mecz** | Rozgrywanie szybkich gier użytkownika (sparingi, mecze towarzyskie). |
| 2 | **Sędziowanie turniejów** | Sędziowanie meczów w ramach turnieju organizowanego przez ligę (np. w klubie). |

Każda z nich ma **inny sposób uwierzytelnienia** i **inny cel** – nie mieszamy ich ze sobą.

---

## Uwierzytelnienie

### 1. Szybki mecz → logowanie na konto

- **Wymagane:** konto użytkownika (email + hasło).
- **Cel:** użytkownik loguje się na **swoje konto gracza**, żeby m.in. tworzyć lobby, zapraszać znajomych, rozgrywać sparingi.
- **W aplikacji mobilnej:** przycisk „Zaloguj się” oraz wybór „Szybki mecz” na ekranie głównym prowadzą do ekranu logowania (email + hasło).
- **API (backend):** `POST /api/account/login` z `{ "email", "password" }` → zwraca `{ "token", "user" }`.
- **Po sukcesie:** użytkownik trafia do **lobby Szybki mecz** (tworzenie lobby, zaproszenia znajomych).
- **Łączenie w lobby (Szybki mecz):** tylko przez **system zaproszeń między znajomymi**. W trybie Szybki mecz nie ma opcji „dołącz po kodzie” – kod służy wyłącznie do trybu turniejowego.

### 2. Turniej → uwierzytelnienie kodem

- **Wymagane:** **kod turnieju** podany przez administratora (organizatora turnieju).
- **Cel:** osoba sędziująca mecze w danym turnieju wpisuje kod i dostaje dostęp **tylko do meczów tego turnieju**. Nie jest wymagane konto – wystarczy kod.
- **Backend:** kody są **generowane przy starcie turnieju** (panel webowy). Administrator rozdaje je sędziom.
- **W aplikacji mobilnej:** wybór „Turniej” na ekranie głównym prowadzi do ekranu z jednym polem: **Kod turnieju**. Po wpisaniu kodu użytkownik „wchodzi” do turnieju.
- **API (backend):** `POST /api/login` z `{ "code" }` → zwraca `{ "token", "tournamentId" }`.
- **Po sukcesie:** użytkownik trafia do **listy meczów tego turnieju** (widzi tylko mecze przypisane do tego kodu).

---

## Kontekst: dlaczego rozdzielamy te dwie rzeczy

- **Marcin** (Olsztyn) gra w Olsztyńskiej Lidze Darta. Z **Kubą** (też z ligi) gra sparingi – sędziują je w aplikacji w sekcji **Szybki mecz** (logowanie na konto, zaproszenia, lobby).
- W sobotę w klubie Marcin **uruchamia turniej** w ramach Olsztyńskiej Ligi (panel webowy). System generuje **kody logowania do turnieju**. Marcin rozdaje je osobom sędziującym (m.in. Kubie).
- **Kuba** przy tarczy wpisuje w aplikacji kod w sekcji **Turniej** i dostaje listę **tylko meczów tego turnieju** (Olsztyńska Liga).
- **Grzegorz** (inna liga w twentySix) prowadzi własną ligę. Uruchamia swój turniej – system generuje **jego** kody. Sędziowie wpisują **jego** kod i widzą **tylko mecze tego turnieju**.
- Dzięki temu: **kod Marcina ≠ kod Grzegorza** → sędziowie widzą tylko turniej, który ich dotyczy. Nie ma mieszania lig/turniejów.

---

## Podsumowanie przepływów

| Użytkownik chce… | Wybiera w aplikacji | Wpisuje / loguje się | Trafia do |
|------------------|----------------------|----------------------|------------|
| Sędziować turniej (np. w sobotę w klubie) | **Turniej** | **Kod turnieju** (od administratora) | Lista meczów **tego** turnieju |
| Grać sparingi (Szybki mecz) | **Szybki mecz** lub **Zaloguj się** | **Email + hasło** (konto gracza) | Lobby Szybki mecz |

---

## Rola backendu i aplikacji mobilnej

| Obszar | Backend (twentySix) | Aplikacja mobilna |
|--------|---------------------|--------------------|
| **Konto gracza** | Rejestracja, logowanie (`/api/account/login`), token Sanctum dla użytkownika | Ekrany: logowanie (email+hasło), lobby Szybki mecz, zaproszenia |
| **Turniej** | Generowanie kodów przy starcie turnieju, logowanie kodem (`POST /api/login`), token dla sędziego, lista meczów po `tournamentId` | Ekran: wpisanie kodu turnieju → lista meczów tego turnieju |
| **Szybki mecz** | Lobby, zaproszenia (API wymagające tokena użytkownika) | Lobby: tworzenie + dołączanie **wyłącznie przez zaproszenia** (bez kodu). Rozgrywka. |

---

## Techniczne skróty (dla developera)

- **Konto (Szybki mecz):** `POST /api/account/login` → `{ token, user }` → w kontekście: `accessToken`, `tournamentId: null` → ekran startowy po zalogowaniu: **QuickGameLobby**.
- **Turniej (kod):** `POST /api/login` → `{ token, tournamentId }` → w kontekście: `accessToken`, `tournamentId` → ekran startowy: **MatchList** (lista meczów dla `tournamentId`).

---

## Scoring rozgrywek (wspólny model)

Jeden silnik gry (legi + wizyty + sync); kontekst to adapter, nie osobna aplikacja.

| Kontekst | Mobile | Backend API | Zapis wyniku |
|----------|--------|-------------|--------------|
| **Trening** | `useGameScoring` bez transportu, lokalne reducery | brak | nie |
| **Quick FFA** | `createFfaTransport` + private WS | `/api/quick-game/lobby/{id}/ffa/*` | `QuickGameFfaScoringService` → `quick_games` |
| **Turniej tablet** | `createTournamentTransport` + public WS | `/api/group-games/{id}/scoring/*`, `/api/playoff-games/{id}/scoring/*` | `GameScoringService` → `games` / `playoff_games` + finalizacja turnieju |

**Po meczu (achievementy):**

- Turniej: mecz kończy **scoring API** (`closeLeg`); mobile wysyła achievementy przez `POST /api/game/update` (tylko gdy mecz już `FINISHED`).
- Quick FFA: mecz kończy **FFA scoring**; achievementy przez `POST /api/quick-game/update` z `gameId`.

**Sesja mobile (konto gracza):**

- Logowanie: `POST /api/account/login` → token Sanctum `mobile-app`, ważność **30 dni** (`config/mobile.php`, `MOBILE_TOKEN_TTL_DAYS`).
- **Sliding window:** `POST /api/account/session/refresh` (Bearer) — rotacja tokena, nowy TTL od momentu użycia aplikacji.
- Wylogowanie: `POST /api/account/logout` — revoke bieżącego tokena.
- Aplikacja: „Zapamiętaj mnie” zapisuje token w SecureStore (nie hasło).

**Wspólna logika wizyt (backend):** `VisitRecorder` — walidacja bust/remaining/checkout, kolejka tur, liczenie legów. Mobile: `helpers/gameScoring/` (`normalizeScoringState`, `applyGameScoringState`).

### Tryb per-dart — synchronizacja online (mobile)

Ustawienie **Każdy rzut osobno** zmienia tylko UI wpisywania. **Nie** synchronizuj po każdej pojedynczej lotce.

Wysyłka do API (`recordVisit` / `closeLegWithWinner`) następuje wyłącznie gdy:
- skończono **3 lotki** w wizycie;
- **bust** (lotka 1–3);
- **checkout** — koniec lega na lotce 1, 2 lub 3 (`closedLeg: true`, poprawne `dartsInVisit`).

Lotki 1–2 w trwającej wizycie (bez bust/checkout) — stan tylko lokalnie, bez POST. Szczegóły implementacji: reguła `twentysix-engineering.mdc` w mobile.

Szczegóły refaktoru: [`docs/game-scoring-unification.md`](docs/game-scoring-unification.md).

---

## Dane do testowego logowania (DemoDataSeeder)

Po uruchomieniu **`php artisan migrate:fresh --seed`** (lub sam `DemoDataSeeder` przy już zmigrowanej bazie) w bazie są dane demo do **logowania na konto** (panel WWW / opcjonalnie Szybki mecz, jeśli logujesz się tym samym kontem):

| Email           | Hasło    | Gracz (nazwa)           |
|-----------------|----------|-------------------------|
| gracz1@test.pl  | password | Jan Kowalski            |
| gracz2@test.pl  | password | Anna Nowak              |
| gracz3@test.pl  | password | Piotr Wiśniewski        |
| gracz4@test.pl  | password | Maria Wójcik            |
| gracz5@test.pl  | password | Tomasz Kamiński         |
| gracz6@test.pl  | password | Katarzyna Lewandowska   |
| gracz7@test.pl  | password | Marcin Zieliński        |
| gracz8@test.pl  | password | Magdalena Szymańska     |

**Znajomi i zaproszenia (`DemoPlayersSeeder`):**

- **Znajomi (zaakceptowane):** 1–2, 1–3, 1–4, 2–3, 3–5, 4–6, 5–7, 6–8, 7–8 (np. Jan widzi Annę, Piotra, Marię).
- **Oczekujące zaproszenia:** Jan→Tomasz (5), Anna→Katarzyna (6), Marcin→Jan (1), Maria→Magdalena (8).
- **Odrzucone:** Piotr→Magdalena (3→8).

**Konto z aktualnego `DemoDataSeeder`** (po `php artisan migrate:fresh --seed`) — logowanie w **panelu WWW** (zarządzanie ligą, sezonem, turniejami demo):

| Kontekst | Email | Hasło |
|----------|-------|-------|
| Administrator demo (liga „twentySix — Liga demonstracyjna”, sezon, turnieje) | `demo-admin@twentysix.local` | `password` |

- **Logowanie na konto (Szybki mecz):** konta `gracz1@test.pl` … `gracz8@test.pl` (hasło `password`) tworzy **`DemoPlayersSeeder`** — znajomi i zaproszenia (pending / rejected) między nimi. Administrator turniejów: `demo-admin@twentysix.local` / `password`.
- **Kod turnieju:** kody powstają **przy starcie turnieju** w panelu. Turniej demo **„Mistrzostwa 32 — pełny bracket (demo)”** startuje już w seedzie — gotowe kody sędziowskie są w tabeli `login_codes` (pole `tournament_id` wskazuje ten turniej). Dla nowego turnieju uruchomionego ręcznie w panelu kody pojawią się tak samo po starcie.

---

*Ostatnia aktualizacja: czerwiec 2026*
