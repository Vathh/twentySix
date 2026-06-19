# Konwencje i ustalenia projektowe

## Nazewnictwo - Game vs Match

**WAŻNE:** W projekcie zawsze używamy słowa **Game** zamiast **Match** do określania rozgrywek — w kodzie, URL-ach, trasach, zmiennych i kolumnach DB.

### Powód
Słowo `match` jest słowem kluczowym w PHP (`match ($x) { ... }`). Unikamy go w identyfikatorach, żeby kod był jednoznaczny. Dotyczy to także URL-i (`/games/...`) i nazw tras (`games.show`), bo odnoszą się do rozgrywki, nie do składni języka.

### Zasady
- ✅ Używamy: `Game`, `GameLeg`, `GameService`, `QuickGame`, `GroupGame`, `PlayoffGame`
- ❌ Unikamy: `Match`, `MatchLeg`, `MatchService` (z wyjątkiem przypadków gdzie jest to konieczne w kontekście PHP)
- ❌ Mobile / API scoring: `matchScoring`, `useMatchScoring`, `resolveMatchContext` — używaj **`gameScoring`**, **`useGameScoring`**, **`resolveGameContext`**

### Przykłady
- `GameLeg` zamiast `MatchLeg`
- `GameLegService` zamiast `MatchLegService`
- `GameLegDTO` zamiast `MatchLegDTO`
- Tabela w bazie: `game_legs` zamiast `match_legs`

## Architektura

### Domain-Repository-Service Pattern
Projekt ścisłe przestrzega wzorca Domain-Repository-Service:
- **Domain**: Logika biznesowa, encje domenowe
- **Repository**: Dostęp do danych, operacje na bazie
- **Service**: Orkiestracja, koordynacja między warstwami

### Struktura katalogów
```
app/
├── Domain/          # Encje domenowe
├── Repositories/    # Warstwa dostępu do danych
├── Services/        # Warstwa logiki biznesowej
├── DTO/            # Data Transfer Objects
├── Models/         # Eloquent models
└── Http/
    └── Controllers/ # Kontrolery API i web
```

## Użytkownik a gracz (Player)

- **Każdy zarejestrowany użytkownik (`User`) ma dokładnie jeden powiązany rekord `Player`** (`players.user_id` jest unikalne). Nie zakładamy istnienia konta bez gracza.
- **Rejestracja** (API: `App\Http\Controllers\Api\AuthController::register`, web: `App\Http\Controllers\AuthController::register`) tworzy najpierw `User`, potem `Player` z wybraną nazwą — tak samo należy postępować w **seedach i testach**, gdy tworzysz użytkownika „jak po rejestracji”.
- **Goście** turniejowi / ligowi to `Player` z `user_id = null`; dotyczy to tylko gości, nie kont użytkowników.

## Turniej a schematy punktów (`PointScheme`)

- **Liczba uczestników** przy starcie (`TournamentService::tryCreateGroupGames`, `count($playerIds)`) musi wpadać w co najmniej jeden przedział `min_players`–`max_players` w `point_schemes` (obecnie seed pokrywa **4–80**). Dobór: `PointSchemeService::findByPlayersAmount`; przy braku dopasowania — wyjątek.
- **Reguły** w `point_scheme_rules` opisują punkty za miejsca w grupie oraz za etapy drabinki (`EIGHT`, `QUARTER`, `THIRD`, `FINAL`). **Większy turniej = wyższa skala punktów** przy tym samym etapie (porównaj np. zwycięstwo w finale między przedziałami w seedzie).
- Przedziały w seedzie są **rozłączne** (4–8, 9–16, …, aż do 73–80). Jeśli kiedyś dodasz nakładające się zakresy, `PointSchemeService::findByPlayersAmount` wybiera schemat z **największym `min_players`** (wyższa skala przy granicy).
- **Źródło prawdy** i zmiany przedziałów / liczb: `database/seeders/PointSchemeSeeder.php` — nowe przedziały tylko po uzgodnieniu; pilnuj monotoniczności i spójności z kodem wyników.

## Szczegóły meczu, wizyty i statystyki (plan wdrożenia)

Dotyczy meczów turniejowych (`games`, `playoff_games`) oraz towarzyskich (`quick_games`). W kodzie i na WWW używamy **Game** — route `/games/{type}/{id}`.

### Strona WWW rozgrywki

- Jeden kontroler / widok (`GameViewController@show`) z adapterem ładującym `group` | `playoff` | `quick`.
- Etykieta typu: **turniejowy** (grupa / playoff + nazwa turnieju) lub **towarzyski** (quick game).
- Linki: kafelek drabinki playoff, wynik w tabeli grupy → ta sama strona.
- **Skuteczność na double (mecz):** agregat z legów — `sum(double_successes) / sum(double_attempts)` tylko dla legów, gdzie `double_tracked = true` (per gracz).

### Duble (per gracz, per leg)

- Liczone **tylko w trybie pojedynczych lotek** (`per_dart` w ustawieniach gracza). Przy wpisywaniu **sumy wizyty** — `double_tracked = false`, `double_attempts` / `double_successes` = `null`.
- Reguła w mobilce: gdy wynik da się zamknąć dublem, każda **lotka w segmencie double** to próba; trafienie = sukces (np. przy 32: 7, 5, D10 → 2 próby, 1 sukces).
- Wartości zapisywane **raz przy zamknięciu lega** (`POST .../legs/{leg}/close`), nie w każdej wizycie.
- **Turniej:** na razie jedno urządzenie (jeden wpisuje wizyty). **Quick game:** możliwe dwa urządzenia — każdy gracz może mieć inny tryb wpisywania → `double_tracked` **osobno dla każdego gracza** w danym legu.

### Tabela `game_visits` (zapis na żywo, po każdej wizycie)

Wizyta należy do **lega** (`game_leg_id`), nie bezpośrednio do meczu.

| Pole | Opis |
|------|------|
| `game_leg_id` | FK |
| `player_id` | FK |
| `visit_number` | Kolejność wizyty w legu (1, 2, 3…) |
| `score` | Suma punktów wizyty (0–180) |
| `remaining_before` | Wynik przed wizytą |
| `remaining_after` | Wynik po wizycie |
| `darts_in_visit` | Ile lotek w tej wizycie (1–3); także **która lotka zamknęła lega** (wartość 1–3 przy checkoutcie) |
| `closed_leg` | Czy ta wizyta zamknęła lega |
| `bust` | Bust w tej wizycie |
| `is_voided` | Cofnięta wizyta (undo) |
| `client_visit_id` | UUID z mobilki (idempotencja) |

- Duble **nie** są w `game_visits`.
- Na koniec meczu **nie** wysyłamy bulk tablicy `visits` — są już w bazie.
- **Undo (v1):** tylko w **otwartym** legu; wizyta dostaje `is_voided = true`, backend przelicza stan i broadcast (Pusher).  
  **TODO (później):** undo po zamknięciu lega (cofnięcie zamknięcia, korekta wyniku meczu).

### Tabela `game_leg_player_stats` (opcja B — cache per gracz per leg)

Unikalność: `(game_leg_id, player_id)`. Uzupełniane przy **zamknięciu lega** (backend może część policzyć z wizyt; duble z payloadu mobilki).

| Pole | Opis |
|------|------|
| `game_leg_id` | FK |
| `player_id` | FK |
| `leg_average` | Średnia w legu (3 lotki) |
| `first_nine_average` | Średnia z **pierwszych trzech wizyt** w legu |
| `highest_visit` | Najwyższe podejście (max `score` wizyt w legu) |
| `highest_finish` | Najwyższy checkout (finish) w legu |
| `darts_thrown` | Łączna liczba lotek w legu |
| `checkout_dart` | Numer lotki (1–3), która zamknęła lega — spójne z `darts_in_visit` na wizycie zamykającej |
| `double_tracked` | Czy w tym legu liczono duble dla tego gracza |
| `double_attempts` | nullable |
| `double_successes` | nullable |

Średnie meczowe, najlepszy leg itd. na stronie meczu — agregacja z legów / `game_leg_player_stats`, bez przeliczania wszystkich wizyt przy każdym wejściu.

### Backend jako źródło prawdy (stan licznika)

- Mobilka wysyła **wynik wizyty** (+ `client_visit_id`, `darts_in_visit`, `remaining_before`, flagi).
- Backend zapisuje wizytę, **przelicza** średnie / remaining / stan meczu z aktywnych wizyt (`is_voided = false`) i **zwraca pełny stan** w odpowiedzi API.
- **Pusher** — ten sam stan do drugiego urządzenia (quick game) i podglądu live na WWW (bez pollingu).
- Przy zamknięciu lega: zapis `game_leg_player_stats` + duble z mobilki.

### API — osobne endpointy, wspólna logika

Osobne ścieżki (łatwiejsza autoryzacja i walidacja), **wspólne serwisy** (np. `GameScoringService`, `GameScoringStateBuilder`) — bez duplikacji reguł biznesowych.

Przykładowy przepływ (kształt roboczy):

1. `POST .../legs` — start lega (rekord `game_legs` + wiersze `game_leg_player_stats` z `double_tracked` wg ustawień graczy).
2. `POST .../legs/{leg}/visits` — wizyta na żywo.
3. `POST .../legs/{leg}/visits/undo` — cofnięcie ostatniej wizyty (v1: tylko otwarty leg).
4. `POST .../legs/{leg}/close` — koniec lega (zwycięzca, statystyki lega, duble).
5. `POST .../finish` — koniec meczu (wynik legów, achievements; wizyty i statystyki legów już w bazie).

Osobne prefiksy dla: mecz grupowy, playoff, quick game — ten sam kontrakt payloadu gdzie to możliwe.

### Demo seed

Dla pełnego turnieju 32-osobowego w `DemoDataSeeder`: fikcyjne `game_visits` + `game_leg_player_stats` (w tym duble tam, gdzie `double_tracked`).
