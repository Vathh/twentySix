# Logika biznesowa – system DartScore (backend + aplikacja mobilna)

**Wspólny dokument** dla obu aplikacji działających w syntezie:

- **DartScore** – backend (Laravel) + panel webowy (organizacja lig, turniejów, generowanie kodów)
- **Suwalska-Liga-Darta-MobileApp** – aplikacja mobilna (sędziowanie, szybki mecz)

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
- **Grzegorz** (Suwałki) prowadzi Suwalską Ligę Darta. Uruchamia swój turniej – system generuje **jego** kody. Sędziowie Grzegorza wpisują **jego** kod i widzą **tylko mecze Suwalskiej Ligi**.
- Dzięki temu: **kod Marcina ≠ kod Grzegorza** → sędziowie widzą tylko ten turniej, który ich dotyczy. Nie ma mieszania lig/turniejów.

---

## Podsumowanie przepływów

| Użytkownik chce… | Wybiera w aplikacji | Wpisuje / loguje się | Trafia do |
|------------------|----------------------|----------------------|------------|
| Sędziować turniej (np. w sobotę w klubie) | **Turniej** | **Kod turnieju** (od administratora) | Lista meczów **tego** turnieju |
| Grać sparingi (Szybki mecz) | **Szybki mecz** lub **Zaloguj się** | **Email + hasło** (konto gracza) | Lobby Szybki mecz |

---

## Rola backendu (DartScore) i mobilki

| Obszar | Backend (DartScore) | Aplikacja mobilna |
|--------|---------------------|--------------------|
| **Konto gracza** | Rejestracja, logowanie (`/api/account/login`), token Sanctum dla użytkownika | Ekrany: logowanie (email+hasło), lobby Szybki mecz, zaproszenia |
| **Turniej** | Generowanie kodów przy starcie turnieju, logowanie kodem (`POST /api/login`), token dla sędziego, lista meczów po `tournamentId` | Ekran: wpisanie kodu turnieju → lista meczów tego turnieju |
| **Szybki mecz** | Lobby, zaproszenia (API wymagające tokena użytkownika) | Lobby: tworzenie + dołączanie **wyłącznie przez zaproszenia** (bez kodu). Rozgrywka. |

---

## Techniczne skróty (dla developera)

- **Konto (Szybki mecz):** `POST /api/account/login` → `{ token, user }` → w kontekście: `accessToken`, `tournamentId: null` → ekran startowy po zalogowaniu: **QuickGameLobby**.
- **Turniej (kod):** `POST /api/login` → `{ token, tournamentId }` → w kontekście: `accessToken`, `tournamentId` → ekran startowy: **MatchList** (lista meczów dla `tournamentId`).

---

## Dane do testowego logowania (DemoDataSeeder)

Po uruchomieniu `php artisan db:seed` (lub `DatabaseSeeder` z `DemoDataSeeder`) w bazie są użytkownicy testowi do **logowania na konto** (Szybki mecz):

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

- **Logowanie na konto (Szybki mecz):** dowolny z powyższych emaili + hasło `password`.
- **Kod turnieju:** kody są generowane **przy starcie turnieju** w panelu webowym (DartScore). W seedzie nie ma gotowych kodów – trzeba uruchomić turniej w panelu, żeby wygenerować kody i użyć ich w aplikacji mobilnej w sekcji „Turniej”.

---

*Ostatnia aktualizacja: luty 2025*
