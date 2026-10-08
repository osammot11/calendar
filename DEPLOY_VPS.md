# Aggiornamento VPS

Procedura rapida per pubblicare su VPS IONOS le modifiche gia committate e pushate su GitHub.

## 1. Da locale

Controlla, committa e pusha:

```bash
cd "/Users/tommasogiovannoni/Desktop/Progetti web/calendar"
git status
git add .
git commit -m "Descrivi la modifica"
git push origin main
```

Se non ci sono modifiche locali da committare, salta `git add`, `git commit` e `git push`.

## 2. Da VPS

Accedi come `root`:

```bash
ssh root@IP_DELLA_VPS
```

Aggiorna il progetto:

```bash
cd /var/www/calendar
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data /var/www/calendar/storage /var/www/calendar/bootstrap/cache /var/www/calendar/database
chmod -R ug+rw /var/www/calendar/storage /var/www/calendar/bootstrap/cache /var/www/calendar/database
systemctl reload php8.4-fpm
systemctl reload nginx
```

Se il servizio PHP non e `php8.4-fpm`, trova quello corretto:

```bash
systemctl list-units --type=service | grep php
```

Poi ricarica quello giusto, per esempio:

```bash
systemctl reload php8.4-fpm
```

## 3. Configurazione Slack

Dopo il deploy della modifica Slack, aggiorna `.env` sulla VPS:

```bash
cd /var/www/calendar
nano .env
```

Aggiungi o aggiorna:

```env
SLACK_BOT_TOKEN=xoxb-...
SLACK_SIGNING_SECRET=...
SLACK_ALLOWED_USER_IDS=U...
SLACK_TASK_DRAFT_TTL_MINUTES=60
```

Poi applica migrazioni e cache config:

```bash
php artisan migrate --force
php artisan config:clear
php artisan config:cache
systemctl reload php8.4-fpm
systemctl reload nginx
```

Nella Slack App configura:

```text
Slash command: https://calendar.tommasogiovannoni.com/slack/commands/task
Interactivity: https://calendar.tommasogiovannoni.com/slack/interactions
Events API: https://calendar.tommasogiovannoni.com/slack/events
Bot event: message.im
Scopes: commands, chat:write, im:write, im:history
```

## 4. Token calendario iPhone/Google

Solo se il token ICS non e gia presente nel `.env`:

```bash
cd /var/www/calendar
openssl rand -hex 32
nano .env
```

Aggiungi o aggiorna:

```env
CALENDAR_FEED_TOKEN=TOKEN_GENERATO
```

Poi aggiorna la cache config:

```bash
php artisan config:clear
php artisan config:cache
systemctl reload php8.4-fpm
```

Test feed:

```bash
curl -I https://calendar.tommasogiovannoni.com/calendar-feed/TOKEN_GENERATO.ics
```

Risposta attesa:

```text
HTTP/2 200
content-type: text/calendar; charset=utf-8
```

URL da aggiungere su iPhone:

```text
https://calendar.tommasogiovannoni.com/calendar-feed/TOKEN_GENERATO.ics
```

## 5. Controlli utili

Log Laravel:

```bash
cd /var/www/calendar
tail -n 100 storage/logs/laravel.log
```

Stato servizi:

```bash
systemctl status nginx --no-pager
systemctl status php8.4-fpm --no-pager
```

## 6. Integrazione ChatGPT con OAuth Laravel

Il server MCP e servito direttamente da Laravel su `https://calendar.tommasogiovannoni.com/mcp/calendar`. Usa il login gia presente e Laravel Passport per OAuth con PKCE. Non servono Auth0, un servizio Node o un secondo virtual host Nginx.

Al primo deploy di questa integrazione, verifica nel `.env` sulla VPS:

```env
APP_URL=https://calendar.tommasogiovannoni.com
```

Poi esegui come `root`:

```bash
cd /var/www/calendar
git pull origin main
composer install --no-dev --optimize-autoloader
composer audit --no-dev
php artisan migrate --force
php artisan passport:keys
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
chown -R www-data:www-data storage bootstrap/cache database
chmod -R ug+rw storage bootstrap/cache database
systemctl reload php8.4-fpm
```

Le chiavi `storage/oauth-private.key` e `storage/oauth-public.key` vengono generate solo sulla VPS, sono ignorate da Git e vanno conservate nei backup. Non ripetere `passport:keys --force` su un'installazione gia collegata: invaliderebbe i token esistenti.

Controlla che questi endpoint rispondano tramite HTTPS:

```bash
curl -i https://calendar.tommasogiovannoni.com/.well-known/oauth-protected-resource/mcp/calendar
curl -i https://calendar.tommasogiovannoni.com/.well-known/oauth-authorization-server
```

Entrambi devono dare `200` con JSON. Se Nginx blocca `/.well-known/`, consenti questo prefisso nel server block Laravel prima della regola che nega i file nascosti, poi verifica con `nginx -t` e ricarica Nginx.

In ChatGPT web, aggiungi un server MCP personalizzato con URL `https://calendar.tommasogiovannoni.com/mcp/calendar` e autenticazione OAuth senza Client ID/Secret statici (registrazione dinamica). Al primo utilizzo, accedi con l'account del calendario e approva il consenso. Il redirect di ChatGPT viene riconosciuto automaticamente purche resti sul dominio `https://chatgpt.com`.

Per le release successive basta la normale procedura di aggiornamento in alto. Non rigenerare le chiavi Passport.
