# Następne kroki — twentySix MVP (po przerwie)

**Dla nowego agenta:** zacznij tutaj. Ostatni stan: **lipiec 2026**, staging RC działa, quick game FFA + Reverb sync **OK** (Expo Go + APK 1.0.8), **rejestracja + SMTP (Resend)** **OK**.

Powiązane: [`plan_krok6_release_rc.md`](plan_krok6_release_rc.md), [`post_mvp_poprawki.md`](post_mvp_poprawki.md), [`deploy_staging.md`](deploy_staging.md).

---

## Co już działa (nie trzeba od nowa)

| Obszar | Stan |
|--------|------|
| **Staging VPS** | `https://dartscore.studiokam.pl`, PHP/nginx/MySQL/Reverb/queue |
| **Reverb sync** | Laravel → `127.0.0.1:8080`, telefony → WSS `:443` przez nginx `/app` |
| **Mobile APK** | EAS `preview`, wersja **1.0.8** — lobby live sync + mecz quick game |
| **Mobile dev** | `twentysix-mobile/.env` (staging, gitignored) + `npm start` + Expo Go |
| **Backend fix broadcast** | `config/broadcasting.php` — `REVERB_BROADCAST_*` / domyślnie `127.0.0.1:8080` |
| **Mobile fix Pusher** | `helpers/getPusherConstructor.js` — lazy static `require`, unwrap exportu |
| **Diagnostyka RC** | Panel „Diagnostyka sync” gdy `EXPO_PUBLIC_REVERB_DEBUG=true` |
| **SMTP / rejestracja** | Resend, domena `studiokam.pl`, `noreply@studiokam.pl` — mail weryfikacyjny + login OK |

**Staging (publiczne, bez haseł):**

- API: `https://dartscore.studiokam.pl/api`
- Reverb key (mobile + backend `.env`): `28e001f35df29406bc8a144a39a4ef4a`
- Demo (staging): **brak seeda** — testerzy rejestrują się sami (SMTP OK)
- VPS SSH: `185.235.69.21`, app w `/var/www/twentysix-backend`, git jako `www-data`

**Repo GitHub:**

- Backend: `https://github.com/Vathh/twentySix.git`
- Mobile: `https://github.com/Vathh/twentySix-MobileApp.git`

---

## Priorytet 1 — domknięcie kroku 6 (RC → tag MVP)

| # | Zadanie | Uwagi |
|---|---------|--------|
| 1 | ~~**Zaktualizować** [`plan_krok6_release_rc.md`](plan_krok6_release_rc.md) — odhaczyć 6.2–6.4, 6.2.6~~ | ✅ lipiec 2026 |
| 2 | ~~**Smoke regresji** na staging~~ | ✅ ręcznie (quick + turniej) |
| 3 | ~~**SMTP na staging**~~ | ✅ Resend + rejestracja web/mobile |
| 4 | ~~**Decyzja o seedzie staging**~~ | ✅ bez seeda — czysta baza, tylko rejestracja |
| 5 | **Tag `v1.0.0-mvp`** | Backend + mobile, notatka release (co wchodzi, znane ograniczenia) |
| 6 | **Ostatni EAS preview** po tagu | Jeśli były zmiany po 1.0.8 — jeden build „oficjalny” dla testerów |

---

## Priorytet 2 — przed produkcją (nie blokuje RC)

| # | Zadanie | Plik / doc |
|---|---------|------------|
| 1 | **Prod deploy bez `--seed`** | [`post_mvp_poprawki.md`](post_mvp_poprawki.md) |
| 2 | **SMTP prod** | `.env.staging.example` → MAIL_* |
| 3 | **Wyłączyć panel debug w prod mobile** | `EXPO_PUBLIC_REVERB_DEBUG` tylko w `eas.json` → `preview`, nie `production` |
| 4 | **Serwer: `git pull` + `config:cache`** | Upewnić się, że `broadcasting.php` i nginx `/app` + `/apps` są na VPS |

---

## Priorytet 3 — poprawki jakości (post-MVP, z backlogu)

| # | Zadanie | Doc |
|---|---------|-----|
| 1 | Ikony: `@fortawesome` → `@expo/vector-icons` w całym mobile | [`post_mvp_poprawki.md`](post_mvp_poprawki.md) |
| 2 | Drag kolejności graczy w lobby — `DraggableFlatList` jako główny scroll, nie w `ScrollView` | j.w. |
| 3 | Usunąć / ukryć „Diagnostyka sync” w buildach produkcyjnych | opcjonalnie zostawić tylko w preview |

---

## Workflow dev mobile (żeby nie robić EAS przy każdej linii)

1. `twentysix-mobile/.env` — staging URL (patrz `.env.example`)
2. `npm start` → Expo Go (ta sama Wi‑Fi)
3. Po OK lokalnie → **`eas build --profile preview`** (weryfikacja release/Hermes)

Uwaga: bugi bundlera (np. Pusher) czasem widać **tylko w APK**, nie w Expo Go.

---

## Kluczowe pliki Reverb (mobile)

| Plik | Rola |
|------|------|
| `helpers/getPusherConstructor.js` | Lazy load Pusher, unwrap exportu Hermes |
| `helpers/createReverbPusher.js` | Konfiguracja + authorizer `/broadcasting/auth` |
| `hooks/useQuickGameLobbyRealtime.js` | Lobby WS |
| `hooks/useGameScoringRealtime.js` | Mecz WS |
| `components/ReverbDebugPanel.jsx` | Panel diagnostyki |
| `eas.json` → `preview.env` | Zmienne builda staging |

## Kluczowe pliki Reverb (backend)

| Plik | Rola |
|------|------|
| `config/broadcasting.php` | PHP → `127.0.0.1:8080` |
| `app/Events/QuickGameLobbyUpdated.php` | Event `lobby.updated` |
| `routes/channels.php` | Autoryzacja kanału lobby |
| `docs/deploy_staging.md` | nginx `/app` + `/apps`, systemd reverb |

---

## Świadomie poza scope / później

- Krykiet, komunikator, premium
- Pełna regresja automatyczna mobile
- Nowy agent: reguły w `.cursor/rules/` + `CONVENTIONS.md` + `docs/product.md`

---

*Po kolejnej większej zmianie zaktualizuj sekcję „Co już działa” i priorytety w tym pliku.*
