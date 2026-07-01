# Wdrożenie staging — twentySix MVP

Plan nadrzędny: [`plan_krok6_release_rc.md`](plan_krok6_release_rc.md).

Założenia: Ubuntu 22.04+ (lub inny Linux), nginx, PHP 8.2+, MySQL 8, Node 20+ (tylko do buildu assetów).

---

## 1. Baza danych

```sql
CREATE DATABASE dartscore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dartscore'@'localhost' IDENTIFIED BY 'silne-haslo';
GRANT ALL ON dartscore.* TO 'dartscore'@'localhost';
FLUSH PRIVILEGES;
```

---

## 2. Kod aplikacji

```bash
cd /var/www
git clone <url-repozytorium> twentysix-backend
cd twentysix-backend
composer install --no-dev --optimize-autoloader
cp .env.staging.example .env
php artisan key:generate
# Edytuj .env: APP_URL, DB_*, REVERB_*, MAIL_*
npm ci
npm run build
php artisan migrate --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Uprawnienia:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

---

## 3. Nginx (HTTP — przed certbot)

Przykład `/etc/nginx/sites-available/twentysix`:

```nginx
server {
    listen 80;
    server_name staging.example.com;
    root /var/www/twentysix-backend/public;

    index index.php;
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/twentysix /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

---

## 4. HTTPS (Let's Encrypt)

```bash
certbot --nginx -d staging.example.com
```

Po certyfikacie w `.env`:

```env
APP_URL=https://staging.example.com
REVERB_HOST=staging.example.com
REVERB_PORT=443
REVERB_SCHEME=https
SESSION_SECURE_COOKIE=true
```

```bash
php artisan config:cache
```

---

## 5. Reverb (WebSocket)

Osobny proces systemd, np. `/etc/systemd/system/twentysix-reverb.service`:

```ini
[Unit]
Description=twentySix Reverb WebSocket
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/twentysix-backend
ExecStart=/usr/bin/php artisan reverb:start --host=0.0.0.0 --port=8080
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

Nginx **proxy WSS** (fragment w bloku `server` HTTPS):

```nginx
location /app {
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_pass http://127.0.0.1:8080;
}
```

Dostosuj ścieżkę do konfiguracji Reverb w Laravel — domyślnie klient łączy się przez port/schemat z `REVERB_*`.

```bash
systemctl enable --now twentysix-reverb
```

---

## 6. Kolejka (opcjonalnie, zalecane)

```ini
# /etc/systemd/system/twentysix-queue.service
[Service]
User=www-data
WorkingDirectory=/var/www/twentysix-backend
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3
Restart=always
```

---

## 7. Smoke test po wdrożeniu

1. `https://staging.example.com` — strona główna bez błędów 500.
2. Rejestracja → mail SMTP → link → login web.
3. `POST /api/account/login` z mobile (build ze staging URL).
4. Quick game 2P — sync tur (Reverb).
5. Live meczu turniejowego na webie.

---

## 8. Aktualizacja wersji

```bash
cd /var/www/twentysix-backend
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
systemctl restart twentysix-reverb twentysix-queue
```

---

## Mobile

Build wskazujący na staging: patrz [`../twentysix-mobile/.env.example`](../twentysix-mobile/.env.example) i krok **6.4** w [`plan_krok6_release_rc.md`](plan_krok6_release_rc.md).
