# Dokumentacja twentySix — indeks

**Nowy agent / developer:** zacznij od [`product.md`](product.md) (wizja) i [`NEXT_STEPS.md`](NEXT_STEPS.md) (co robić dalej).

**Przewodnik po aplikacji (dla ludzi / GitHub):** [`przewodnik.md`](przewodnik.md) — flow turnieju, tablety, quick game + miejsca na zrzuty.

---

## Źródła prawdy

| Dokument | Rola |
|----------|------|
| [`product.md`](product.md) | Wymagania produktowe, reguły gry, MVP vs później |
| [`NEXT_STEPS.md`](NEXT_STEPS.md) | **Start dla agenta** — backlog rozwoju |
| [`przewodnik.md`](przewodnik.md) | Opis działania aplikacji (flow + screenshoty) |
| [`../IMPLEMENTED_FEATURES.md`](../IMPLEMENTED_FEATURES.md) | Mapa kod ↔ MVP (backend) |
| [`../../twentysix-mobile/IMPLEMENTED_FEATURES.md`](../../twentysix-mobile/IMPLEMENTED_FEATURES.md) | Mapa kod ↔ MVP (mobile) |
| [`../CONVENTIONS.md`](../CONVENTIONS.md) | Konwencje kodu (Game vs Match, undo lega, …) |
| [`../LOGIKA_BIZNESOWA.md`](../LOGIKA_BIZNESOWA.md) | Przepływy biznesowe web + mobile |

---

## Operacje

| Dokument | Rola |
|----------|------|
| [`deploy_staging.md`](deploy_staging.md) | Runbook VPS — **tylko gdy użytkownik prosi o deploy** |
| [`instrukcja_testerow_mvp_v1.md`](instrukcja_testerow_mvp_v1.md) | Onboarding testerów (rejestracja, APK, scenariusze minimum) |

**MVP v1:** tag `v1.0.0-mvp` (backend `b1f3193`, mobile `9a39d28`, lipiec 2026). Stan funkcji: `IMPLEMENTED_FEATURES.md` + sekcja „Status MVP” w `product.md`.

---

## Testy manualne (regresja)

| Plik | Obszar |
|------|--------|
| [`scenariusze_manualne_quick_game_mvp_4e.md`](scenariusze_manualne_quick_game_mvp_4e.md) | Quick game FFA, presence, walkower |
| [`scenariusze_manualne_turniej_mvp.md`](scenariusze_manualne_turniej_mvp.md) | Turniej tablet + web |
| [`scenariusze_manualne_web_gosc_krok3.md`](scenariusze_manualne_web_gosc_krok3.md) | Podgląd gościa, live, zaproszenia |

Testy automatyczne: `php artisan test` (backend), `npm run test:game-scoring` (mobile).

---

## Referencje techniczne

Czytaj przed zmianami w danym obszarze (nie są planem prac):

| Dokument | Rola |
|----------|------|
| [`design_quick_game_ffa_sync_4c2.md`](design_quick_game_ffa_sync_4c2.md) | Sync FFA 3–8 (`each_own`) |
| [`design_tournament_formats_se_de.md`](design_tournament_formats_se_de.md) | SE/DE: bye, miejsca, GF, bracket |
| [`design_scoring_result_delivery.md`](design_scoring_result_delivery.md) | Outbox / idempotentne closeLeg |
| [`game-scoring-unification.md`](game-scoring-unification.md) | `useGameScoring` + transporty |

---

## Reguły Cursor

`.cursor/rules/` w obu repozytoriach — skrót produktu i inżynierii dla agentów AI.
