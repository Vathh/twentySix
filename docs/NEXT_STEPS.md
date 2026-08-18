# Następne kroki — twentySix

**Dla nowego agenta:** zacznij od [`product.md`](product.md) (wizja) i tego pliku. Indeks: [`README.md`](README.md).

**Stan:** lipiec–sierpień 2026 — MVP v1 otagowane (`v1.0.0-mvp`). Tu pracujemy nad **dalszym rozwojem** (backend + mobile).

---

## Podział odpowiedzialności

| Obszar | Kto |
|--------|-----|
| **Kod, testy lokalne, dokumentacja produktowa** | Agent / dev w Cursorze |
| **Deploy na VPS, migracje na serwerze, build EAS/APK** | **Właściciel projektu** — sam albo na wyraźną prośbę |

**Agent nie proponuje** deployu staging/prod ani nowego EAS, chyba że użytkownik o to poprosi. Runbook: [`deploy_staging.md`](deploy_staging.md).

---

## Domyślny workflow agenta

1. Czytaj [`product.md`](product.md) przed większymi zmianami.
2. Implementuj w `twentysix-backend` / `twentysix-mobile`.
3. Weryfikuj lokalnie: `php artisan test`, `npm run test:game-scoring` (mobile), Expo Go.
4. Aktualizuj docs tylko gdy zmiana produktowa lub konwencja tego wymaga.
5. **Nie** commituj / pushuj / deployuj bez prośby użytkownika.

---

## Backlog (otwarte)

| Temat | Stan |
|-------|------|
| **Liga piramidowa (web)** | W toku — organizacja ma ligi ze szczeblami, składem, sezonem ligowym, tabelą, WO/rezygnacją; scoring tabletem później |
| **Awatary graczy** | Zaplanowane (jeszcze bez planu) — upload + profil/listy; limity + fallback inicjałów; później crop/CDN |
| **Live drabinka / playoff (web WS)** | Później — zakładka Playoff dziś SSR (F5); live WS jest dla macierzy grup + pojedynczego meczu. Wzorzec: `TournamentGroupMatrixLiveService` |
| **Prowadzenie organizacji typu Apagon** | Później — zakres UX do ustalenia przy planie |
| **Tryby gry** (kolejne) | Później — Bob's 27, Around the Clock, Catch 40 i Cricket 60 są w treningu + quick |
| **Autoryzacja scoringu / anty-nadużycia** | Daleka przyszłość — kto może `recordVisit` / `closeLeg`; audyt. Nieblocker turnieju klubowego |
| **Aktualizacja APK w apce (self-update)** | Później — przy starcie komunikat „nowa wersja” → pobranie APK → instalator Androida (systemowy dialog Zainstaluj). Nie OTA (`expo-updates` — tylko JS). Na czas testów: endpoint `latestVersion` + URL APK + to samo podpisywanie keystore. Po wejściu do Play: In-App Updates. iOS: tylko TestFlight / App Store. **Nie implementować teraz.** |

---

## Niedawno domknięte (skrót)

Lobby prune TTL · sędziowanie web (kod tabletu) · SE/DE · QR zgłoszenia · Cricket (trening + quick) · **Bob's 27 (trening + quick)** · **Around the Clock (trening + quick)** · **Catch 40 (trening + quick)** · **Cricket 60 (trening + quick)** · panel platformy · outbox scoringu · push zaproszeń · format gry X01 + presety organizacji · Arena Dark · przewodnik (tekst; brak PNG).

Szczegóły w kodzie / [`../IMPLEMENTED_FEATURES.md`](../IMPLEMENTED_FEATURES.md). Designy SE/DE i FFA: [`design_tournament_formats_se_de.md`](design_tournament_formats_se_de.md), [`design_quick_game_ffa_sync_4c2.md`](design_quick_game_ffa_sync_4c2.md).

---

## Tech debt / poza zakresem

- Deploy VPS, `migrate`, EAS — po stronie właściciela (chyba że poprosi)
- Komunikator, premium, E2E mobile (Maestro/Detox)
- Krykiet w turnieju — poza zakresem (jest trening + quick)
- Bob's 27 w turnieju — poza zakresem (jest trening + quick)
- Around the Clock w turnieju — poza zakresem (jest trening + quick)
- Catch 40 w turnieju — poza zakresem (jest trening + quick)
- Cricket 60 w turnieju — poza zakresem (jest trening + quick)

Reguły: `.cursor/rules/` + [`product.md`](product.md).

---

*Po większej zmianie produktowej zaktualizuj [`product.md`](product.md) i ewentualnie `IMPLEMENTED_FEATURES.md`. Ten plik — gdy zmienia się backlog lub sposób pracy.*
