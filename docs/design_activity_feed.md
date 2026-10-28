# Tablica aktywności

Historie generowane przez system. Zaproszenia i push zostają osobno.

## Dwa ekrany

- **Strona główna** — historia dochodzi, gdy otwarty jest choć jeden jej kanał. Wyciszenie zamyka tylko swój kanał. Każdy fakt jest jednym wierszem. W aplikacji ten widok jest pierwszy po zalogowaniu na konto. Otwiera go logo w lewym górnym rogu. Dolny pasek nie ma osobnego przycisku.
- **Profil** — wszystkie historie bohatera. Kto je widzi, decyduje `users.profile_feed_visibility` (domyślnie `public`).

Kanały czyta definicja typu, nie kolumna na wierszu.

## Nowy typ

1. Klasa w `app/Domain/Activity/Definitions/` (`type`, `channels`, `subjectType`, `replacesExisting`, `kind`, `sentence`). `kind` to etykieta w nagłówku karty. Nowy fakt dostaje istniejący rodzaj albo nowy, ustalony przy dodawaniu.
2. Wpis w `ActivityRegistry`.
3. Jedna linia `ActivityRecorder` w miejscu, gdzie fakt się commituje.
4. Akapit w tym pliku.
5. Test: jeden wiersz, zdanie, kanały i widoczność profilu.

Zdanie idzie jednym szablonem: imię, „właśnie”, co się stało. Karta nie składa tekstu osobno dla każdego typu.

## Karta

Nagłówek: okrągły awatar i nazwa, pod spodem czas i rodzaj osiągnięcia. Treść to zdanie. Pod spodem serce z liczbą i „Komentarze” z liczbą. Ten sam układ na stronie głównej i na zakładce Tablica, na webie i w aplikacji.

Wpis zawodnika podpisuje zawodnik. Link do profilu jest tylko wtedy, gdy ma konto — gość zostaje zwykłym napisem, bo jego profil zwraca 404. Wygrana turnieju jest wpisem organizacji: w nagłówku jest nazwa i logo klubu, a w zdaniu nadal imię zwycięzcy.

| Rodzaj | Kod | Typy |
| --- | --- | --- |
| Wygrana | `win` | `tournament.champion` |
| 180 | `maximum` | `player.first_180` |
| Odznaka | `badge` | `player.badges` |
| Rekord | `record` | `player.personal_best` |

## Typy

| Kod | Kanały strony głównej | Rodzaj | Zdanie | Status |
| --- | --- | --- | --- | --- |
| `tournament.champion` | znajomi i organizacja | Wygrana | „Andrzej właśnie wygrał turniej Piątkowe Granie w Apagon.” | emiter: koniec turnieju i korekta podium |
| `player.first_180` | znajomi | 180 | „Andrzej właśnie zaliczył swoje pierwsze 180!” | emiter: wizyta X01 (turniej, liga, quick FFA) |
| `player.badges` | znajomi | Odznaka | „Andrzej właśnie zdobył odznaki: 180 — Brąz, QF 12 — Żelazo.” | emiter: koniec meczu grupowego, playoff i ligowego; odznaki kariery turnieju osobno |
| `player.personal_best` | znajomi | Rekord | „Andrzej właśnie poprawił życiowy rekord średniej meczowej: 85,40.” | emiter: ten sam mecz. Średnia tylko gdy przebija poprzednią. Finish od 100 i lotka poniżej 20 także za pierwszym razem |

Jeden mecz i jeden zawodnik to jeden wpis odznak, nawet gdy poziomów wskoczyło kilka. Skok przez próg zostawia tylko osiągnięty stopień. Seria zwycięstw wchodzi do wpisu meczu. Podium, udział i tytuł turnieju są osobnym wpisem zawodnika, nie turnieju. Życiówki z tego samego meczu też są jednym wpisem. Podium 2–3 i wygrana quick game nie wchodzą na tablicę.

## Widoczność strony głównej

Kanał znajomych jest otwarty, gdy oglądający jest aktorem albo znajomym aktora, kanał jest włączony (własne historie zostają przy wyłączonym kanale) i aktor nie jest wyciszony. Siebie wyciszyć się nie da.

Kanał organizacji jest otwarty, gdy oglądający jest w składzie, kanał organizacji jest włączony i ta organizacja nie jest wyciszona.

Wyciszony Andrzej i niewyciszony Dart Suwałki: wygrana turnieju zostaje, bo dochodzi przez organizację. Zamknięte oba kanały: historii nie ma.

## Profil

Zakładka **Tablica** na profilu gracza (web i mobile), obok **Podsumowania**. `GET /api/players/{player}/feed` i web `players.feed`.

- `public` — wszyscy, także gość
- `friends` — znajomi i właściciel
- `friends_and_circle` — znajomi, właściciel oraz wspólny skład organizacji albo przyjęte zaproszenie do tego samego turnieju

## Personalizacja

- `feed_channel_settings` — wyłącznik kanału. Brak wiersza oznacza kanał włączony.
- `feed_mutes` — `(user_id, subject_type, subject_key)`. Na start `organization` i `user`. Kolejny filtr to nowy `subject_type`, bez nowej tabeli.

## Później

Mistrz sezonu ligowego (te same kanały i rodzaj Wygrana co wygrana turnieju). Podium 2–3 i wygrana quick game nie wchodzą na tablicę. Zaproszenia zostają przy pushu.

### Komentarze i reakcje

Pisać i reagować może osoba, która widzi wpis na tablicy profilu bohatera. Właściciel zawsze. Wejście na stronę główną nie wystarcza. Zgadnięte id zwraca 404.

- `activity_comments` — treść, autor, opcjonalna odpowiedź o jeden poziom. Autor kasuje swój komentarz, a wraz z nim odpowiedzi.
- `activity_reactions` — jedna reakcja na osobę, na wpis albo komentarz. Na start `like`. Drugie tapnięcie tego samego rodzaju zdejmuje reakcję.

Liczniki liczone przy odczycie. Karta niesie `canEngage`, `commentCount` i `reactions`. API i te same adresy sesji webowej: lista i zapis komentarzy, usunięcie, `PUT` reakcji na wpis i na komentarz. Pasek pod kartą jest na webie i w aplikacji.
