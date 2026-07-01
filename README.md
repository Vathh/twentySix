# twentySix — backend i panel webowy

**twentySix** to system do organizacji lig i turniejów darterskich: panel webowy (Laravel), API i WebSocket dla aplikacji mobilnej.

- **Wizja produktu i MVP:** [`docs/product.md`](docs/product.md)
- **Stan implementacji vs MVP:** [`IMPLEMENTED_FEATURES.md`](IMPLEMENTED_FEATURES.md)
- **Plan domknięcia v1:** [`docs/plan_mvp_domkniecie.md`](docs/plan_mvp_domkniecie.md)
- **Logika biznesowa (web + mobile):** [`LOGIKA_BIZNESOWA.md`](LOGIKA_BIZNESOWA.md)

## Repozytoria

| Część | Folder | Stack |
| ----- | ------ | ----- |
| Web + API | `twentysix-backend` (ten projekt) | Laravel |
| Mobile | `twentysix-mobile` | React Native / Expo |

> Jeśli foldery nadal nazywają się `DartScore` / `Suwalska-Liga-Darta-MobileApp`, zob. [`docs/RENAME_FOLDERS.md`](docs/RENAME_FOLDERS.md).

---

## Uruchomienie dev (LAN / lokalnie)

### Wymagania

- PHP 8.2+, Composer, Node.js
- **MySQL** — baza `dartscore` (dev), opcjonalnie `dartscore_test` (testy)

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

## Testy automatyczne

Baza testowa (np. `dartscore_test` w `.env` / `phpunit.xml`):

```bash
php artisan test
```

Stan docelowy MVP: **176 passed, 14 skipped** (lipiec 2026). Pominięte testy: widoki wymagające Vite manifest, legacy bulk POST wyniku quick game.

---

## Scenariusze manualne (checklisty)

| Obszar | Plik |
|--------|------|
| **Staging / prod** | [`docs/deploy_staging.md`](docs/deploy_staging.md) |
| **Plan krok 6** | [`docs/plan_krok6_release_rc.md`](docs/plan_krok6_release_rc.md) |
| Quick game FFA + presence | [`docs/scenariusze_manualne_quick_game_mvp_4e.md`](docs/scenariusze_manualne_quick_game_mvp_4e.md) |
| Turniej tablet + web | [`docs/scenariusze_manualne_turniej_mvp.md`](docs/scenariusze_manualne_turniej_mvp.md) |
| Web gość | [`docs/scenariusze_manualne_web_gosc_krok3.md`](docs/scenariusze_manualne_web_gosc_krok3.md) |

---

## Marka

- **Nazwa produktu:** twentySix
- **Logo / ikona:** znak **26** (tylko grafika)
