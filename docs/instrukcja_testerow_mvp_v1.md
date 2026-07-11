# Instrukcja dla testerów — twentySix MVP v1

Wersja: release candidate (`v1.0.0-mvp`).  
Backend staging: `https://dartscore.studiokam.pl`

---

## Konto testowe

**Staging nie ma gotowych kont** — każdy tester **rejestruje się sam**:

1. Web: [https://dartscore.studiokam.pl/register](https://dartscore.studiokam.pl/register)  
   albo mobile: ekran **Utwórz konto** (z logowania).
2. Sprawdź email → kliknij link **Potwierdź email**.
3. Zaloguj się w aplikacji lub na webie.

**Dev lokalny** (organizator): po `migrate --seed` nadal są konta `gracz1@test.pl` … / `password` — tylko na laptopie, nie na stagingu.

---

## Aplikacja mobilna

1. Zainstaluj build APK / Expo internal (link od organizatora).
2. **Quick game online** wymaga konta — **zarejestruj się** (patrz wyżej) albo zaloguj po weryfikacji email.
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
