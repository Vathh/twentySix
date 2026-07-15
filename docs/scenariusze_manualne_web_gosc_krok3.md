# Scenariusze manualne — Web gość

Źródło: [`product.md`](product.md). Regresja: [`instrukcja_testerow_mvp_v1.md`](instrukcja_testerow_mvp_v1.md).

Powiązane: turniej tablet — [`scenariusze_manualne_turniej_mvp.md`](scenariusze_manualne_turniej_mvp.md).

---

## Wymagania środowiska

| Usługa | Komenda | Uwagi |
|--------|---------|--------|
| MySQL | baza `dartscore` | Po `php artisan migrate:fresh --seed` lub istniejący stan po testach turniejowych |
| API + web | `php artisan serve --host=0.0.0.0` | Dostęp z tego samego komputera i z LAN |
| Reverb | `php artisan reverb:start --host=0.0.0.0` | **Wymagany** do testu live (scenariusz 5) |
| Przeglądarka | okno **incognito** | Gość = brak ciasteczek sesji |

**Konta:**

| Rola | Email | Hasło | Do czego |
|------|-------|-------|----------|
| Gość | — | — | Okno incognito, bez logowania |
| Admin | `demo-admin@twentysix.local` | `password` | Scenariusze 10–12 (druga karta / zwykłe okno) |

**Dane demo:** turniej **Turniej 6-osobowy (faza grupowa)** w lidze **twentySix — Liga demonstracyjna** → sezon **Sezon jesienny 2025**. Jeśli turniej jest już po fazie grupowej / playoff — nadal OK do podglądu; do live potrzebny mecz **w trakcie** (tablet) lub tymczasowo zostawiony niedokończony.

---

## Przygotowanie (jednorazowo)

1. Uruchom backend: `php artisan serve --host=0.0.0.0`
2. Uruchom Reverb: `php artisan reverb:start --host=0.0.0.0`
3. Otwórz **okno incognito** — to będzie „gość”
4. Otwórz **zwykłe okno** — zaloguj się jako `demo-admin@twentysix.local` (do regresji admina na końcu)

---

## Część A — Gość (incognito)

Wykonuj po kolei. Przy każdym punkcie: **OK?** ☐ / uwagi.

### A1. Strona główna i nawigacja

1. Wejdź na `http://127.0.0.1:8000/` (lub IP komputera w LAN).
2. **Oczekiwane:** nagłówek „twentySix”, linki: Strona główna, Ligi, Sezony, Turnieje, Szukaj graczy, **Zaloguj się**. **Brak** „Wyloguj”, „Mój profil”, panelu znajomych z prawej.
3. Kliknij **Zobacz turnieje** (lub link Turnieje w menu).
4. **Oczekiwane:** lista turniejów, **brak** przycisku „Stwórz nowy turniej” w rogu.

### A2. Liga → sezon → turniej

5. Menu **Ligi** → kliknij **twentySix — Liga demonstracyjna** (lub pierwszą ligę z listy).
6. **Oczekiwane:** opis ligi, tabela top 40, lista sezonów. **Brak** bocznego panelu „Zarządzanie ligą”.
7. Kliknij sezon **Sezon jesienny 2025**.
8. **Oczekiwane:** daty sezonu, lista turniejów. **Brak** panelu „Zarządzanie sezonem”.
9. Kliknij **Turniej 6-osobowy (faza grupowa)** (lub inny wystartowany turniej z seeda).
10. **Oczekiwane:** nazwa turnieju, data, zakładki **Wyniki / Playoff / Grupy / Osiągnięcia**. **Brak** sekcji „Kody logowania na tablety” i **brak** panelu „Rozpocznij turniej” z boku.

### A3. Zakładki turnieju

11. Zakładka **Grupy** — macierz meczów i tabele grup. Kliknij **zielony** link wyniku (mecz zakończony) lub **pomarańczowy** (mecz w trakcie, jeśli jest).
12. **Oczekiwane:** przejście na `/games/group/{id}` bez prośby o logowanie.
13. Zakładka **Playoff** (jeśli turniej w playoff) — kliknij parę w drabince.
14. **Oczekiwane:** szczegóły meczu lub live (zależnie od statusu).
15. Zakładka **Osiągnięcia** — lista achievementów turnieju (może być pusta).
16. Zakładka **Wyniki** — końcowa klasyfikacja / podium (jeśli turniej zakończony).

### A4. Szczegóły meczu (zakończony)

17. Otwórz mecz **zakończony** (status „Zakończony”, bez linku „Podgląd live”).
18. **Oczekiwane:**
    - wynik w legach, statystyki meczu (średnie, 60+, 80+…),
    - sekcja „Legi i wizyty”,
    - **brak** formularza „Korekta wyniku / walkower”,
    - tekst **nie** mówi „możesz wymusić wynik końcowy poniżej”.

### A5. Live meczu (w trakcie)

> Jeśli nie ma meczu `in_progress`: na tablecie rozpocznij dowolny mecz grupowy i **nie kończ go** (np. po 1 legu), potem wróć do incognito.

19. W zakładce **Grupy** kliknij **Podgląd na żywo** (pomarańczowy wynik) **lub** ze strony meczu przycisk **Podgląd live**.
20. URL: `/games/group/{id}/live` (lub `playoff`).
21. **Oczekiwane:**
    - wynik i tura gracza widoczne,
    - badge połączenia przechodzi w stan live (Reverb),
    - po wpisaniu wizyty na **tablecie** strona live **aktualizuje się bez ręcznego F5**,
    - **brak** redirectu na login.

22. Dokończ mecz na tablecie. Odśwież live (lub poczekaj) — **oczekiwane:** redirect / komunikat zakończenia; wejście ponowne na `/live` przekierowuje na **szczegóły** meczu.

### A6. Szczegóły meczu (w trakcie) — gość

23. Wejdź na `/games/group/{id}` dla meczu **in_progress** (nie live).
24. **Oczekiwane:**
    - badge „Na żywo”, link **Podgląd live**,
    - tekst „Mecz w trakcie.” (**bez** wzmianki o wymuszaniu wyniku),
    - **brak** formularza korekty.

### A7. Profil gracza

25. Menu **Szukaj graczy** → wpisz fragment imienia (np. `Jan` lub `gracz`) → wybierz gracza z kontem.
26. **Oczekiwane:** profil, statystyki, historia meczów — **bez** przycisku „Dodaj do znajomych” (tylko dla zalogowanych).

### A8. Blokada akcji admina (gość)

27. W pasku adresu wpisz ręcznie: `/tournaments/{id}/start` (id turnieju z seeda).
28. **Oczekiwane:** **redirect na logowanie** (302), brak formularza startu.
29. Wpisz ręcznie: `/seasons/{id}/edit` (id sezonu demo).
30. **Oczekiwane:** **redirect na logowanie** lub 403 — **brak** formularza edycji sezonu.

---

## Część B — Admin (zalogowany) — regresja

W **zwykłym oknie** (zalogowany `demo-admin@twentysix.local`).

### B1. Korekta wyniku

31. Wejdź w dowolny mecz grupowy `/games/group/{id}`.
32. **Oczekiwane:** sekcja **Korekta wyniku / walkower** widoczna; dla meczu w trakcie tekst o wymuszeniu wyniku.
33. Ustaw walkower **2:0** dla jednego gracza (lub zmień wynik) → Zapisz.
34. **Oczekiwane:** sukces, tabela grup / drabinka zaktualizowana po odświeżeniu turnieju.

### B2. Start turnieju i kody (skrót)

35. Utwórz **nowy** turniej w sezonie demo (opcjonalnie) **lub** otwórz turniej ze statusem `created`.
36. Wejdź w **Rozpocznij turniej** — **oczekiwane:** formularz uczestników, zaproszeń, start — **bez** 403.
37. Na stronie wystartowanego turnieju jako admin: **oczekiwane:** sekcja **Kody logowania na tablety** (gość tego nie widzi — por. A2 pkt 10).

### B3. Zaproszenie (opcjonalna regresja mobile)

38. Wyślij zaproszenie do użytkownika testowego z webu.
39. Na mobile: **Zaproszenia** → akceptacja turniejowa.
40. **Oczekiwane:** zaproszenie widoczne i akceptowalne (bez regresji API).

---

## Checklist podsumowujący

| # | Scenariusz | OK? | Uwagi |
|---|------------|-----|-------|
| A1 | Nawigacja gościa | ☐ | |
| A2 | Liga → sezon → turniej | ☐ | |
| A3 | Zakładki turnieju | ☐ | |
| A4 | Szczegóły meczu (finished) | ☐ | |
| A5 | Live + sync WS | ☐ | |
| A6 | Szczegóły meczu (in_progress) | ☐ | |
| A7 | Profil gracza | ☐ | |
| A8 | Blokada URL admina | ☐ | |
| B1 | Korekta / walkower (admin) | ☐ | |
| B2 | Start + kody tabletów | ☐ | |
| B3 | Zaproszenie → mobile | ☐ | opcjonalnie |

**Krok 3 uznany za zamknięty**, gdy wszystkie wiersze A1–A8 i B1–B2 są OK (B3 opcjonalnie).

Powiązane: [`scenariusze_manualne_turniej_mvp.md`](scenariusze_manualne_turniej_mvp.md) · testy auto: `php artisan test`.
