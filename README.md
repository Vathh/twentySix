# twentySix

System do prowadzenia **organizacji i turniejów darterskich** oraz meczów ze znajomymi — panel webowy + aplikacja mobilna.

| | |
| --- | --- |
| **Web + API** | ten repozytorium ([twentySix](https://github.com/Vathh/twentySix)) — Laravel |
| **Mobile** | [twentySix-MobileApp](https://github.com/Vathh/twentySix-MobileApp) — React Native / Expo |

---

## Jak to działa? (przewodnik)

Pełny opis flow gry — od utworzenia turnieju, przez QR i logowanie tabletu, po sędziowanie i szybki mecz:

### → [docs/przewodnik.md](docs/przewodnik.md)

W skrócie:

1. **Web:** organizacja → turniej → zaproszenia / QR zgłoszeń / goście  
2. **Web:** start turnieju (grupy+playoff / SE / DE) → powstaje **jeden kod + QR** na tablety  
3. **Mobile:** Turniej → skan QR lub wpisanie kodu → wybór meczu → sędziowanie  
4. **Web:** tabele i drabinka aktualizują się na żywo  

Miejsca na zrzuty ekranu są już w przewodniku — wrzuć pliki do [`docs/assets/screenshots/`](docs/assets/screenshots/) (lista nazw: [`README w folderze`](docs/assets/screenshots/README.md)).

---

## Dokumentacja techniczna

- **Wizja produktu i MVP:** [`docs/product.md`](docs/product.md)
- **Stan implementacji vs MVP:** [`IMPLEMENTED_FEATURES.md`](IMPLEMENTED_FEATURES.md)
- **Co robić dalej:** [`docs/NEXT_STEPS.md`](docs/NEXT_STEPS.md)
- **Indeks dokumentacji:** [`docs/README.md`](docs/README.md)
- **Logika biznesowa (web + mobile):** [`LOGIKA_BIZNESOWA.md`](LOGIKA_BIZNESOWA.md)

---

## Uruchomienie dev (LAN / lokalnie)

### Wymagania

- PHP 8.2+, Composer, Node.js
- **MySQL** — baza `dartscore` (dev), opcjonalnie `dartscore_test` (testy)
- Klient CLI `mysql` w PATH (np. `C:\xampp\mysql\bin`) — potrzebny do feature testów ładujących dump schematu

### Backend — pierwsze uruchomienie

```bash
composer install
cp .env.example .env
php artisan key:generate
```

W `.env` ustaw m.in.:

```env
APP_URL=http://127.0.0.1:8000
DB_DATABASE=dartscore
SESSION_DRIVER=database
```

Migracja i dane demo:

```bash
php artisan migrate --seed
```

Frontend (Tailwind / Vite):

```bash
npm install
npm run dev
```

### Backend — każda sesja dev (3 terminale)

Telefon w tej samej sieci Wi‑Fi co komputer — **nie** używaj `0.0.0.0` w URL po stronie klienta.

| Terminal | Komenda | Uwagi |
|----------|---------|--------|
| 1 | `php artisan serve --host=0.0.0.0` | Web + API pod `http://<IP>:8000` |
| 2 | `npm run dev` | Assety Vite |
| 3 | `php artisan reverb:start --host=0.0.0.0` | WebSocket (live meczu, FFA sync) |

Sprawdź IPv4 komputera (np. `192.168.0.28`) — ten adres wpisujesz w mobile.

### Mobile (Expo)

W `twentysix-mobile/helpers/apiConfig.js`:

```javascript
const API_BASE_URL = 'http://192.168.0.28:8000/api';
```

Zamień na **IPv4 komputera** z LAN. W backendzie `.env`: `REVERB_HOST=0.0.0.0` jest OK dla serwera; klient musi łączyć się po realnym IP.

```bash
cd ../twentysix-mobile
npm install
npm start
```

### Konta demo (po `--seed`)

| Rola | Email | Hasło |
|------|-------|-------|
| Admin web | `demo-admin@twentysix.local` | `password` |
| Gracze 1–8 | `gracz1@test.pl` … `gracz8@test.pl` | `password` |

### Rejestracja i potwierdzenie email

- Web: `/register` · Mobile: ekran **Utwórz konto** (z logowania).
- Po rejestracji wysyłany jest link aktywacyjny — **logowanie działa dopiero po kliknięciu**.
- Dev: domyślnie `MAIL_MAILER=log` — treść maila w `storage/logs/laravel.log`.
- Prod/staging: ustaw SMTP w `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST`, …) i poprawny `APP_URL` (link w mailu).

Szczegóły turniejów demo: [`docs/scenariusze_manualne_turniej_mvp.md`](docs/scenariusze_manualne_turniej_mvp.md).

---

## Staging i produkcja (deploy)

**Nigdy** `migrate --seed` na stagingu ani produkcji — użytkownicy rejestrują się sami (web/mobile), weryfikacja email wymaga **SMTP** w `.env`.

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Pełna checklista deploy (gdy potrzebna): [`docs/deploy_staging.md`](docs/deploy_staging.md). Rozwój aplikacji: [`docs/NEXT_STEPS.md`](docs/NEXT_STEPS.md).

| Środowisko | Migracje | Seed demo |
|------------|----------|-----------|
| Dev lokalny | `migrate --seed` OK | `gracz1@test.pl` … |
| Staging / prod | **`migrate --force` tylko** | **nie** |

---

## Testy automatyczne

Baza testowa (np. `dartscore_test` w `.env` / `phpunit.xml`):

```bash
php artisan test
```

---

## Scenariusze manualne (checklisty)

| Obszar | Plik |
|--------|------|
| **Przewodnik użytkownika** | [`docs/przewodnik.md`](docs/przewodnik.md) |
| **Indeks docs** | [`docs/README.md`](docs/README.md) |
| **Staging / prod** | [`docs/deploy_staging.md`](docs/deploy_staging.md) |
| **Następne kroki** | [`docs/NEXT_STEPS.md`](docs/NEXT_STEPS.md) |
| Quick game FFA + presence | [`docs/scenariusze_manualne_quick_game_mvp_4e.md`](docs/scenariusze_manualne_quick_game_mvp_4e.md) |
| Turniej tablet + web | [`docs/scenariusze_manualne_turniej_mvp.md`](docs/scenariusze_manualne_turniej_mvp.md) |
| Web gość | [`docs/scenariusze_manualne_web_gosc_krok3.md`](docs/scenariusze_manualne_web_gosc_krok3.md) |

---

## Marka

- **Nazwa produktu:** twentySix
- **Logo / ikona:** znak **26** (tylko grafika)
