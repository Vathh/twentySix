# Instrukcja dla testerów — twentySix MVP v1

Wersja: release candidate (`v1.0.0-mvp`).  
Backend staging: *(URL uzupełni organizator)*.

---

## Konta testowe (po `migrate --seed`)

| Email | Hasło | Rola |
|-------|-------|------|
| `demo-admin@twentysix.local` | `password` | Admin web (organizator) |
| `gracz1@test.pl` … `gracz8@test.pl` | `password` | Gracze mobile / web |

**Rejestracja nowego konta:** wymaga kliknięcia linku w emailu przed pierwszym logowaniem.

---

## Aplikacja mobilna

1. Zainstaluj build APK / Expo internal (link od organizatora).
2. **Quick game online** wymaga konta — zaloguj się (`gracz1@test.pl` / `password`) lub zarejestruj się.
3. **Turniej tabletowy** — kod z panelu organizatora (web → turniej w fazie grupowej).
4. **Trening** — działa bez konta i bez internetu.

---

## Scenariusze minimum (30–45 min)

### 1. Quick game 2 graczy (mobile)

- Gracz 1 i 2 to **znajomi** (zaproszenie w aplikacji).
- Gracz 1: lobby → invite → start **Każdy na swoim** → BO3.
- Obaj widzą ten sam stan; dokończ mecz.

### 2. Web gość

- Przeglądarka **bez logowania**: ligi, turnieje, tabele.
- Wejdź w **live** trwającego meczu turniejowego (jeśli jest).

### 3. Turniej (tablet + web)

- Tablet: kod turnieju → mecz grupowy BO3.
- Web (admin): tabela się aktualizuje.
- Opcjonalnie: korekta wyniku / walkower na stronie meczu.

---

## Zgłaszanie błędów

Podaj:

1. Co robiłeś (kroki).
2. Co oczekiwałeś vs co się stało.
3. Platforma (Android/iOS, wersja buildu, web Chrome/Firefox).
4. Zrzut ekranu / godzina zdarzenia.

---

## Poza scope MVP (nie testujemy teraz)

Krykiet, push powiadomień, BO5+, live całego turnieju na WS, import starego SQLite.
