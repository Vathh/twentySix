# Poprawki po MVP / staging — lista techniczna

Krótka lista rzeczy do zrobienia po domknięciu testów RC (nie blokuje testeraów na stagingu).

Ostatnia aktualizacja: lipiec 2026.

---

## Mobile — ikony

**Problem:** `@fortawesome/react-native-fontawesome` w buildzie APK (EAS) bywa kapryśne; lobby quick game crashowało po utworzeniu — jedna z możliwych przyczyn (obok drag-listy).

**Do zrobienia:** zamienić ikony w **całym** `twentysix-mobile` na `@expo/vector-icons` (np. `FontAwesome5`), które jest w bundlu Expo i stabilniejsze w release.

**Pliki znane dziś (grep `FontAwesome`):**

| Plik | Użycie |
|------|--------|
| `components/QuickGame/QuickGameLobby.jsx` | tymczasowo tekst zamiast ikon (fix crash staging) |
| `components/Game/GameList.jsx` | `faSync` — odświeżanie listy meczów turniejowych |

Po migracji można rozważyć usunięcie zależności `@fortawesome/*` z `package.json`, jeśli nigdzie indziej nie zostaną.

---

## Mobile — kolejność graczy w lobby (drag)

**Problem:** `DraggableFlatList` **wewnątrz** `ScrollView` — znany antywzorzec; na Androidzie w release często native crash (lobby quick game, lipiec 2026).

**Tymczasowo:** zwykła lista + przycisk „Kolejność losowa”.

**Do zrobienia:** przywrócić przeciąganie **bez** zagnieżdżania:

- `DraggableFlatList` jako **główny** scroll ekranu lobby (host),
- sekcje ustawień (typ gry, BO3, tryb liczenia, zaproszenia) w `ListHeaderComponent` / `ListFooterComponent`,
- **nie** `ScrollView` + `DraggableFlatList` w środku.

Powiązane: `components/QuickGame/QuickGameLobby.jsx`.

---

## Backend / deploy — seed vs produkcja

**Docelowo na produkcji:** **bez** `--seed` — testerzy i użytkownicy **rejestrują się sami** (web lub mobile), weryfikacja email (wymaga **SMTP** w `.env`).

| Środowisko | Migracje | Konta demo (`gracz1@test.pl` …) |
|------------|----------|----------------------------------|
| **Dev lokalny** | `migrate --seed` OK | Wygodne do szybkich testów |
| **Staging (RC)** | `migrate --seed` **opcjonalnie** na start; potem można `migrate:fresh` bez seeda | Albo seed, albo prawdziwa rejestracja + SMTP |
| **Produkcja** | **`php artisan migrate --force` tylko** | **Nie** — wyłącznie rejestracja użytkowników |

Seed (`DemoDataSeeder`, `DemoPlayersSeeder`) zostaje w repo **tylko na dev/staging**, nie jako model produkcyjny.

**Do zrobienia przed prod:** działający SMTP, smoke: rejestracja → mail → link → login → quick game.

---

## Reverb / WebSocket — weryfikacja na serwerze

Po deploy sprawdź (SSH):

```bash
systemctl status twentysix-reverb --no-pager
ss -tlnp | grep 8080
grep -A6 'location /app' /etc/nginx/sites-available/twentysix
```

W `.env`: `BROADCAST_CONNECTION=reverb`, `REVERB_APP_KEY` = ten sam co w `eas.json` (`EXPO_PUBLIC_REVERB_APP_KEY`).

Bez działającego Reverb lobby/mecz **nadaj działają przez polling HTTP** (wolniej), ale sync „na żywo” wymaga WSS.

---

- [`plan_krok6_release_rc.md`](plan_krok6_release_rc.md) — release candidate
- [`instrukcja_testerow_mvp_v1.md`](instrukcja_testerow_mvp_v1.md) — scenariusze testów
