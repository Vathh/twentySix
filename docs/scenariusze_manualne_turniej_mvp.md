# Scenariusze manualne — Turniej MVP

Źródło: [`product.md`](product.md). Regresja: [`instrukcja_testerow_mvp_v1.md`](instrukcja_testerow_mvp_v1.md).

Powiązane: quick game — [`scenariusze_manualne_quick_game_mvp_4e.md`](scenariusze_manualne_quick_game_mvp_4e.md).

---

## Wymagania środowiska

| Usługa | Komenda | Uwagi |
|--------|---------|--------|
| MySQL | baza `dartscore` | Po `php artisan migrate:fresh --seed` |
| API | `php artisan serve --host=0.0.0.0` | Web + API |
| Reverb | `php artisan reverb:start --host=0.0.0.0` | Live meczu na webie |
| Mobile | Expo, `apiConfig.js` → IP komputera | Ten sam LAN co backend |

**Konta demo (seed):**

| Rola | Email | Hasło |
|------|-------|-------|
| Admin web (organizator) | `demo-admin@twentysix.local` | `password` |
| Gracze mobile (opcjonalnie) | `gracz1@test.pl` … `gracz8@test.pl` | `password` |

---

## Dane demo po seedzie

| Turniej | Liga / sezon | Status | Do czego |
|---------|--------------|--------|----------|
| **Turniej 6-osobowy (faza grupowa)** | twentySix — Liga demonstracyjna → Sezon jesienny 2025 | **faza grupowa** (`group`) | Scenariusze **1–4** — granie na tablecie |
| **Mistrzostwa 32 — pełny bracket (demo)** | ta sama liga / sezon | **zakończony** (`finished`) | Tylko podgląd web (wyniki, playoff) — bez grania od zera |

### Kody tabletów (ważne)

Seeder wywołuje `tryCreateGroupGames` — to **od razu startuje** turniej 6-osobowy:

- status → faza grupowa,
- mecze grupowe `scheduled`,
- **2 kody tabletów** (domyślnie = liczba grup).

**Nie trzeba** ponownie klikać „Start turnieju” na webie dla turnieju z seeda.

Kody widoczne po zalogowaniu jako admin:

`Ligi` → **twentySix — Liga demonstracyjna** → **Sezon jesienny 2025** → turniej **Turniej 6-osobowy (faza grupowa)** → sekcja **„Kody logowania na tablety”**.

---

## 1. Faza grupowa — tablet → tabela na webie

1. Web: zaloguj się jako `demo-admin@twentysix.local`.
2. Otwórz turniej **Turniej 6-osobowy (faza grupowa)** i skopiuj jeden kod tabletu.
3. Mobile: **Turniej** → wpisz kod → lista meczów.
4. Wybierz **Grupa 1** lub **Grupa 2** → mecz ze statusem oczekujący (lock).
5. Rozegraj mecz na tablecie w formacie z kreatora turnieju (domyślnie **501 · do 2 legów**, double out) — jedno urządzenie, H2H.
6. Po zakończeniu mecz znika z listy tabletu.
7. Web: zakładka **Grupy** → sprawdź macierz wyników i kolumny W / L / Legi / Pkt / Miejsce.

**Oczekiwane:**

- Mecz w bazie: `games.status = finished`, wynik zgodny z formatem (np. 2:0 / 2:1 legów przy 1 secie).
- Tabela grupy zaktualizowana (punkty, miejsce).
- Drugi tablet z tym samym kodem **nie** może zablokować tego samego meczu (już `in_progress` / `finished`).

**Opcjonalnie w bazie:** `games`, `group_standings`, `game_legs`, `game_visits`.

---

## 2. Playoff — awans w drabince

> Wymaga zakończenia **wszystkich** meczów grupowych w turnieju 6-osobowym.

1. Dokończ pozostałe mecze grupowe (tablet) **lub** użyj korekty web (scenariusz 4) dla szybszego domknięcia fazy grupowej.
2. Web: status turnieju → **playoff** (auto po ostatnim meczu grupowym).
3. Mobile: lista meczów — sekcja **playoff** (płaska lista z etykietą rundy).
4. Rozegraj jeden mecz pucharowy na tablecie.
5. Web: zakładka **Playoff** — zwycięzca awansuje do kolejnej rundy / slotu.

**Oczekiwane:**

- `tournaments.status = playoff` po zamknięciu grup.
- Mecz playoff zapisany w `playoff_games` (lub odpowiednik w API jako `type: playoff`).
- Drabinka na webie odzwierciedla wynik.

---

## 3. Live + achievementy

### 3a. Podgląd live

1. Rozpocznij mecz na tablecie (grupowy lub playoff) — zostaw **w trakcie** (np. po 1 legu) **albo** oglądaj podczas całego meczu.
2. Web: zakładka **Grupy** → kliknij **Podgląd na żywo** (ikona przy meczu w trakcie) **lub** wejdź w mecz → **Podgląd live**.
3. URL: `/games/group/{id}/live` (lub `playoff`).

**Oczekiwane:**

- Wynik i tura aktualizują się przez WebSocket (Reverb), bez ręcznego odświeżania co sekundę.
- Brak lawiny requestów HTTP (tylko WS + ewentualny backup).

### 3b. Achievement po meczu

1. Na tablecie rozegraj wizytę **180** (lub inny achievement: 170+, HF, QF).
2. Dokończ mecz.
3. Web: zakładka turnieju **Osiągnięcia** lub strona meczu `/games/group/{id}`.

**Oczekiwane:**

- `POST /api/game/update` z samymi achievementami po `FINISHED`.
- Wpis w `achievements` powiązany z grą / graczem turniejowym.

---

## 4. Korekta wyniku / walkower (web)

1. Web (admin): wejdź w dowolny mecz grupowy lub playoff — `/games/group/{id}` lub `/games/playoff/{id}`.
2. Sekcja **Korekta wyniku / walkower** → wpisz np. **2:0** (walkower).
3. Zapisz.

**Oczekiwane:**

- Mecz `finished` z nowym wynikiem.
- Tabela grupy / drabinka playoff **przelicza się automatycznie**.
- Jeśli to był ostatni mecz grupowy — turniej przechodzi w playoff (jak przy normalnym zakończeniu).

**Uwaga:** walkower = pełny wynik w jednostce formatu meczu (przy 1 secie / do 2 legów: **2:0**; przy multi-set — w setach). Patrz [`product.md`](product.md).

---

## 5. Regresja tabletu (skrót)

- Tylko mecze **oczekujące** na liście — `w trakcie` i `zakończone` ukryte.
- Lock → drugi tablet dostaje błąd API przy tym samym meczu.
- Po meczu: release, status `finished`.

---

## Checklist kroku 2

| # | Scenariusz | Data | OK? | Uwagi |
|---|------------|------|-----|-------|
| 1 | Faza grupowa | | ☐ | |
| 2 | Playoff | | ☐ | |
| 3a | Live | | ☐ | |
| 3b | Achievementy | | ☐ | |
| 4 | Korekta / walkower | | ☐ | |

Powiązane: web gość — [`scenariusze_manualne_web_gosc_krok3.md`](scenariusze_manualne_web_gosc_krok3.md).

---

## Nowy turniej od zera (opcjonalnie, poza seedem)

Jeśli seed został nadpisany lub chcesz pełny flow organizatora:

1. Web: liga → sezon → utwórz turniej.
2. `/tournaments/{id}/start` — uczestnicy, goście, **min. 4 graczy**, grupy (potęga 2), awans, liczba tabletów.
3. **Start** → kody tabletów + faza grupowa.
4. Dalej scenariusze 1–4.

To regresja **startu turnieju** — szczegółowy flow w kroku 3 planu MVP (zaproszenia).
