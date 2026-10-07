# Następne kroki — twentySix

**Dla nowego agenta:** zacznij od [`product.md`](product.md) (wizja) i tego pliku. Indeks: [`README.md`](README.md).

**Stan:** październik 2026 — MVP v1 otagowane (`v1.0.0-mvp`). Kryteria z [`product.md`](product.md) są spełnione. Poniższy backlog to rzeczy **jeszcze poza produktem**.

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
| **Kariera — metryki poza X01** | Dashboard (okna, źródła, średnia X01, duble, wykresy) jest na **webie i w aplikacji**. Na profilu brakuje zestawów z designu dla cricket / Bob's 27 / ATC / Catch 40 / Cricket 60 oraz personal best tych trybów. Design: [`design_player_career_stats.md`](design_player_career_stats.md) |
| **Trener osobisty** | Kontrakt w kodzie (digest + plan + fallback), **bez UI i bez LLM**. Design: [`design_player_coach.md`](design_player_coach.md) |
| **Tryby gry** (kolejne) | Zakres do ustalenia. Cricket, Bob's 27, Around the Clock, Catch 40 i Cricket 60 są w treningu i quick; w turnieju ich nie ma |
| **Aktualizacja APK w apce (self-update)** | Później — przy starcie komunikat „nowa wersja” → pobranie APK → instalator Androida (systemowy dialog Zainstaluj). Nie OTA (`expo-updates` — tylko JS). Na czas testów: endpoint `latestVersion` + URL APK + to samo podpisywanie keystore. Po wejściu do Play: In-App Updates. iOS: tylko TestFlight / App Store. **Nie implementować teraz.** |
| **Mecz kamerkowy 1v1 (web)** | Później — osobny mecz na stronie, dwie kamerki (twarz + tarcza), obraz WebRTC bezpośrednio między przeglądarkami, sygnalizacja po Reverbie, TURN gdy sieci nie puszczą ruchu. Quick game, turniej i mobile bez kamer. **Nie implementować teraz.** Design: [`design_webcam_match_1v1.md`](design_webcam_match_1v1.md) |

---

## Niedawno domknięte (skrót)

Lobby prune TTL · sędziowanie web (kod tabletu) · SE/DE · QR zgłoszenia · Cricket (trening + quick) · **Bob's 27 (trening + quick)** · **Around the Clock (trening + quick)** · **Catch 40 (trening + quick)** · **Cricket 60 (trening + quick)** · panel platformy · outbox scoringu · push zaproszeń · format gry X01 + presety organizacji · Arena Dark · przewodnik (tekst; brak PNG) · **live grup i drabinki (Reverb)** · **kariera na webie i w aplikacji** (X01, duble, okna, źródła) · **liga jako prowadzenie organizacji typu Apagon** · **broadcast FFA** (wizyty bieżącego lega, zamknięte legi w `legByLegScores`, pole `you` tylko w HTTP) · **awatary graczy** (kadr do kwadratu, JPEG na dysku serwera; CDN później).

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
