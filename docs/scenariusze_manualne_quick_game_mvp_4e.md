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

---

## G. Presence — `each_own`, banner na ekranie meczu

1. Rozpocznij mecz **2P each_own** (scenariusz A).
2. Na telefonie gracza B: zminimalizuj aplikację na ~30 s (symulacja utraty połączenia) albo wyłącz Wi‑Fi na chwilę.
3. Na telefonie gracza A: banner informuje o rozłączeniu B (status `disconnected` w `ffa.state` / presence).
4. Przywróć połączenie B — status wraca do `connected`, banner znika.

**Oczekiwane:** presence w stanie FFA i na kanale WS; bez fałszywego walkovera przy krótkim disconnect.

---

## H. Walkower 2P — gracz opuszcza mecz (`left`)

1. Mecz **2P each_own** w trakcie (np. po pierwszej turze).
2. Gracz B: wyjdź z ekranu scoringu przyciskiem opuszczenia / akcją wysyłającą `status: left` na `POST .../ffa/presence`.
3. Gracz A: mecz kończy się na korzyść A (walkover); oba telefony widzą zakończenie.
4. Po zapisie wyniku: brak aktywnego meczu w `GET /api/quick-game/active-match` dla B.

**Oczekiwane:** tylko **2 graczy + each_own** — przy `left` natychmiastowy walkover; przy 3+ graczach inna logika (kontynuacja / forfeit po dwóch `left`).

---

## I. Powrót do trwającego meczu

1. Rozpocznij mecz **2P each_own**, nie kończ go.
2. Gracz A: wróć na ekran główny (`Home`) — banner **„Wróć do meczu”** (lub równoważny).
3. Tap → ponowne wejście w ten sam lobby / stan FFA.
4. Po scenariuszu H (walkover / koniec): banner **nie** pojawia się ponownie.

**Oczekiwane:** źródło prawdy = `GET /api/quick-game/active-match`, nie stary wpis w AsyncStorage po `left`.
