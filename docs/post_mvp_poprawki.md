# Poprawki po MVP / staging — lista techniczna

Ostatnia aktualizacja: lipiec 2026.

---

## Mobile — ikony ✅

**Zrobione:** `@fortawesome/*` zastąpione `@expo/vector-icons` (`GameList.jsx`). Zależności Font Awesome usunięte z `package.json`.

---

## Mobile — kolejność graczy w lobby (drag) ✅

**Zrobione:** `DraggableFlatList` jako główny scroll ekranu lobby (host, gdy są gracze). Ustawienia w `ListHeaderComponent`, akcje w `ListFooterComponent` — bez zagnieżdżania w `ScrollView`.

---

## Mobile — panel „Diagnostyka sync” ✅

**Zrobione:** panel widoczny **tylko** gdy `EXPO_PUBLIC_REVERB_DEBUG=true|1` (profil `preview` w `eas.json`). Build `production` ma flagę `false`.

---

## Backend / deploy — seed vs produkcja ✅

**Udokumentowane:** [`README.md`](../README.md) (sekcja Staging i produkcja), [`deploy_staging.md`](deploy_staging.md).

| Środowisko | Migracje | Konta demo |
|------------|----------|------------|
| Dev lokalny | `migrate --seed` OK | Wygodne do szybkich testów |
| Staging / prod | **`migrate --force` tylko** | Rejestracja użytkowników + SMTP |

---

## Scoring — undo po zamknięciu lega ✅

**Backend:** cofnięcie ostatniej wizyty **ostatniego** lega otwiera leg ponownie (H2H turniej/quick + FFA quick). Po zakończeniu meczu **turniejowego** — komunikat: użyj korekty na webie.

**Mobile:** przed cofnięciem **zakończonego** lega (gdy bieżący leg jest pusty lub mecz przeszedł do kolejnego lega) — `Alert` z pytaniem „Cofnąć zakończony leg?” (`helpers/gameScoring/undoVisit.js`, `hooks/useGameScoring.js`). Zapobiega przypadkowemu kliknięciu cofania.

Szczegóły backendu: [`CONVENTIONS.md`](../CONVENTIONS.md).

---

## Reverb / WebSocket — weryfikacja na serwerze

Po deploy sprawdź (SSH):

```bash
systemctl status twentysix-reverb --no-pager
ss -tlnp | grep 8080
grep -A6 'location /app' /etc/nginx/sites-available/twentysix
```

W `.env`: `BROADCAST_CONNECTION=reverb`, `REVERB_APP_KEY` = ten sam co w `eas.json` (`EXPO_PUBLIC_REVERB_APP_KEY`).

Bez działającego Reverb lobby/mecz **nadal działają przez polling HTTP** (wolniej), ale sync „na żywo” wymaga WSS.

---

- [`plan_krok6_release_rc.md`](plan_krok6_release_rc.md) — release candidate
- [`instrukcja_testerow_mvp_v1.md`](instrukcja_testerow_mvp_v1.md) — scenariusze testów
