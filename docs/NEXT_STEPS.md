# Następne kroki — twentySix

**Dla nowego agenta:** zacznij od [`product.md`](product.md) (wizja) i tego pliku. Indeks: [`README.md`](README.md).

**Stan:** lipiec 2026 — MVP v1 otagowane (`v1.0.0-mvp`). Tu pracujemy nad **dalszym rozwojem** aplikacji (backend + mobile).

---

## Podział odpowiedzialności

| Obszar | Kto |
|--------|-----|
| **Kod, testy lokalne, dokumentacja produktowa** | Agent / dev w Cursorze |
| **Deploy na VPS, migracje na serwerze, build EAS/APK** | **Właściciel projektu** — sam, gdy uzna za potrzebne, albo **na wyraźną prośbę** |

**Agent nie proponuje ani nie planuje** deployu staging/prod ani nowego EAS, chyba że użytkownik o to poprosi. Runbook na wypadek prośby: [`deploy_staging.md`](deploy_staging.md).

---

## MVP v1 — domknięte ✅

| Element | Stan |
|---------|------|
| Tag git | `v1.0.0-mvp` — backend `b1f3193`, mobile `9a39d28` |
| Staging (ostatni znany) | `https://dartscore.studiokam.pl` |
| Testy auto backend | `php artisan test` |
| E2E mobile w CI | **Nie wdrażamy** |

Mapa funkcji: [`../IMPLEMENTED_FEATURES.md`](../IMPLEMENTED_FEATURES.md).

---

## Domyślny workflow agenta

1. Czytaj [`product.md`](product.md) przed większymi zmianami.
2. Implementuj w `twentysix-backend` / `twentysix-mobile`.
3. Weryfikuj lokalnie: `php artisan test` (backend), `npm run test:game-scoring` (mobile), Expo Go / dev server.
4. Aktualizuj docs tylko gdy zmiana produktowa lub konwencja tego wymaga.
5. **Nie** commituj / pushuj / deployuj bez prośby użytkownika.

---

## Dev lokalny (mobile)

1. `twentysix-mobile/.env` — URL API (patrz `.env.example`)
2. `npm start` → Expo Go

Bugi bundlera (np. Pusher) czasem widać **tylko w APK** — wtedy właściciel robi EAS preview we własnym tempie.

---

## Referencja — staging (informacyjnie)

Gdy użytkownik sam wdraża lub poprosi agenta:

- API: `https://dartscore.studiokam.pl/api`
- Runbook: [`deploy_staging.md`](deploy_staging.md)
- Scenariusze regresji: [`instrukcja_testerow_mvp_v1.md`](instrukcja_testerow_mvp_v1.md), checklisty w [`README.md`](README.md)

---

## Backlog / w toku

| Temat | Plan |
|-------|------|
| **QR zgłoszenia do turnieju** | [`plan_tournament_join_qr.md`](plan_tournament_join_qr.md) — ✅ fazy 1–2 |
| **Cricket** (trening + quick) | [`plan_cricket.md`](plan_cricket.md) — ✅ fazy 1–3 |

### Format gry — pozostałe

1. ~~**Presety formatów w lidze**~~ ✅ lipiec 2026 — `leagues.match_format_presets` → kreator startu turnieju
2. ~~**Chipy BO5 / BO7**~~ — odpuszczone (zbędny skrót UI; format i tak ustawia się pickerami)
3. **Cricket** — [`plan_cricket.md`](plan_cricket.md) (standard scoring; trening + quick; bez turnieju).

## Niedawno domknięte

| Temat | Stan |
|-------|------|
| Presety formatu gry w lidze | ✅ lipiec 2026 — [`plan_konfigurowalny_format_gry.md`](plan_konfigurowalny_format_gry.md) §5.1 |
| Push — zaproszenia (znajomi / turniej / quick game) | ✅ lipiec 2026 — [`plan_push_notifications_zaproszenia.md`](plan_push_notifications_zaproszenia.md) |
| Konfigurowalny format gry — fazy 1–4 (MatchFormat, quick/trening/turniej, walkower) | ✅ lipiec 2026 — [`plan_konfigurowalny_format_gry.md`](plan_konfigurowalny_format_gry.md) |
| Arena Dark (web + mobile) | ✅ lipiec 2026 |

---

## Tech debt (po cleanup lipiec 2026)

Zamknięte: legacy `quick-game/create|active|inProgress`, alias `meta.legsToWin`, `group_standings.match_units_*`, modularizacja `GameScoringScreen` (`offlineVisitFlow` / `onlineVisitFlow` / modale / presence heartbeat).

Pozostaje poza formatem gry:

- Deploy VPS, `migrate` na serwerze, EAS build (chyba że użytkownik poprosi)
- Krykiet, komunikator, premium
- E2E mobile (Maestro/Detox)

Reguły: `.cursor/rules/` + [`product.md`](product.md).

---

*Po większej zmianie produktowej zaktualizuj [`product.md`](product.md) i ewentualnie [`../IMPLEMENTED_FEATURES.md`](../IMPLEMENTED_FEATURES.md). Ten plik — tylko gdy zmienia się sposób pracy lub priorytety rozwoju.*
