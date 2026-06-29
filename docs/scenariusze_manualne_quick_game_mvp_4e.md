# Scenariusze manualne — Quick game MVP (krok 4E)

Źródło: [`product.md`](product.md), [`plan_quick_game_mvp_step4.md`](plan_quick_game_mvp_step4.md).

---

## A. Quick game online — 2 graczy, `each_own`

1. Dwóch znajomych loguje się na mobile.
2. Host: **Quick game online** → utwórz lobby → zaproś znajomego.
3. Znajomy: Zaproszenia → dołącz → **Gotowy**.
4. Host: tryb **Każdy na swoim** → **Rozpocznij** (BO3 = 2 legi).
5. Obaj widzą ten sam stan (FFA sync); każdy wpisuje tylko w swojej turze.
6. Dokończ mecz do 2 legów — w bazie: `quick_game_ffa_sessions`, `quick_games`, `quick_game_results`.
7. Opcjonalnie: achievement (np. 180) trafia przez `POST /api/quick-game/update` z `gameId`.

**Oczekiwane:** brak rozjazdu tur; koniec meczu na obu telefonach jednocześnie.

---

## B. Quick game online — 4 graczy, `one_device`

1. Lobby 4 znajomych, tryb **Jedno urządzenie**.
2. Host wpisuje wszystkich; pozostali tylko podglądają.
3. Sprawdź rotację openera: leg 1 → A, leg 2 → B (nie zwycięzca poprzedniego lega).
4. Wynik w statystykach wszystkich uczestników.

---

## C. Trening — offline (bez internetu)

1. Wyłącz Wi‑Fi / dane na telefonie (lub tryb samolotowy).
2. **Trening** → 4 graczy, imiona lokalne → **Rozpocznij**.
3. Bull modal → rozegraj BO3 na jednym telefonie.
4. Po meczu: komunikat „Wynik nie został zapisany”.
5. Włącz internet — brak nowego rekordu w `quick_games`.

---

## D. Trening — z internetem (bez zapisu)

1. Zalogowany użytkownik, internet włączony.
2. **Trening** (nie Quick game online) → 2 graczy → mecz.
3. Po meczu brak wpisu w statystykach / historii quick game.

---

## E. Regresja — lobby MVP

- Invite nie-znajomego → błąd.
- Dołączenie bez zaproszenia → błąd.
- 9. gracz → błąd.
- Brak kodów lobby (tylko zaproszenie).

---

## F. Turniej (regresja po kroku 3)

- Tablet: lock → scoring API → tabela grupy / playoff.
- Pełna checklista: [`scenariusze_manualne_turniej_mvp.md`](scenariusze_manualne_turniej_mvp.md).
