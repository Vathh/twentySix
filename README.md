# twentySix — backend i panel webowy

**twentySix** to system do organizacji lig i turniejów darterskich: panel webowy (Laravel), API i WebSocket dla aplikacji mobilnej.

- **Wizja produktu i MVP:** [`docs/product.md`](docs/product.md)
- **Stan implementacji vs MVP:** [`IMPLEMENTED_FEATURES.md`](IMPLEMENTED_FEATURES.md)
- **Logika biznesowa (web + mobile):** [`LOGIKA_BIZNESOWA.md`](LOGIKA_BIZNESOWA.md)

## Repozytoria

| Część | Folder | Stack |
| ----- | ------ | ----- |
| Web + API | `twentysix-backend` (ten projekt) | Laravel |
| Mobile | `twentysix-mobile` | React Native / Expo |

> Jeśli foldery nadal nazywają się `DartScore` / `Suwalska-Liga-Darta-MobileApp`, zob. [`docs/RENAME_FOLDERS.md`](docs/RENAME_FOLDERS.md).

## Uruchomienie (skrót)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run dev
php artisan serve
```

Szczegóły demo i kont testowych: `LOGIKA_BIZNESOWA.md`.

## Marka

- **Nazwa produktu:** twentySix
- **Logo / ikona:** znak **26** (tylko grafika)
