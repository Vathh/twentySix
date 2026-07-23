# Dokumentacja twentySix — indeks

**Nowy agent / developer:** zacznij od [`product.md`](product.md) (wizja) i [`NEXT_STEPS.md`](NEXT_STEPS.md) (co robić dalej).

---

## Źródła prawdy

| Dokument | Rola |
|----------|------|
| [`product.md`](product.md) | Wymagania produktowe, reguły gry, MVP vs później |
| [`../IMPLEMENTED_FEATURES.md`](../IMPLEMENTED_FEATURES.md) | Mapa kod ↔ MVP (backend) |
| [`../../twentysix-mobile/IMPLEMENTED_FEATURES.md`](../../twentysix-mobile/IMPLEMENTED_FEATURES.md) | Mapa kod ↔ MVP (mobile) |
| [`../CONVENTIONS.md`](../CONVENTIONS.md) | Konwencje kodu (Game vs Match, undo lega, …) |
| [`../LOGIKA_BIZNESOWA.md`](../LOGIKA_BIZNESOWA.md) | Przepływy biznesowe web + mobile |

---

## Planowanie i operacje

| Dokument | Rola |
|----------|------|
| [`NEXT_STEPS.md`](NEXT_STEPS.md) | **Start dla agenta** — rozwój aplikacji; deploy/EAS po stronie właściciela |
| [`deploy_staging.md`](deploy_staging.md) | Runbook VPS — **tylko gdy użytkownik prosi o deploy** |
| [`instrukcja_testerow_mvp_v1.md`](instrukcja_testerow_mvp_v1.md) | Onboarding testerów (rejestracja, APK, scenariusze minimum) |

**MVP v1:** tag `v1.0.0-mvp` (backend `b1f3193`, mobile `9a39d28`, lipiec 2026). Plany kroków 1–6 i listy poprawek post-MVP zostały usunięte po domknięciu — stan w `IMPLEMENTED_FEATURES.md` i sekcji „Status MVP” w `product.md`.

---

## Testy manualne (regresja)

Checklisty do ręcznej weryfikacji przed release — **nie** są planem prac:

| Plik | Obszar |
|------|--------|
| [`scenariusze_manualne_quick_game_mvp_4e.md`](scenariusze_manualne_quick_game_mvp_4e.md) | Quick game FFA, presence, walkower |
| [`scenariusze_manualne_turniej_mvp.md`](scenariusze_manualne_turniej_mvp.md) | Turniej tablet + web |
| [`scenariusze_manualne_web_gosc_krok3.md`](scenariusze_manualne_web_gosc_krok3.md) | Podgląd gościa, live, zaproszenia |

Testy automatyczne backendu: `php artisan test`. Mobile: `npm run test:game-scoring` (reducer scoringu).

---

## Referencje techniczne (zamknięte projekty)

| Dokument | Rola |
|----------|------|
| [`design_quick_game_ffa_sync_4c2.md`](design_quick_game_ffa_sync_4c2.md) | Design sync FFA 3–8 (`each_own`) — przed zmianami w lobby/scoring |
| [`game-scoring-unification.md`](game-scoring-unification.md) | Architektura `useGameScoring` + transporty (refaktor ✅ 2026) |

---

## Plany do realizacji (backlog)

| Dokument | Temat | Status |
|----------|-------|--------|
| [`plan_push_notifications_zaproszenia.md`](plan_push_notifications_zaproszenia.md) | Push mobile przy zaproszeniach (znajomi / turniej / quick game) | ✅ wdrożone |
| [`plan_konfigurowalny_format_gry.md`](plan_konfigurowalny_format_gry.md) | Konfigurowalny format X01 (fazy 1–4 ✅; faza 5: presety/BO chips/cricket) | ✅ core · 📋 faza 5 |

---

## Reguły Cursor

`.cursor/rules/` w obu repozytoriach — skrót produktu i inżynierii dla agentów AI.
