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

## 6. ChatGPT: server MCP personale

Questa integrazione usa un processo Node separato, raggiungibile pubblicamente solo tramite HTTPS su `/mcp`. Laravel espone un bridge protetto da un segreto; non usare mai la sessione web o il token ICS come credenziale MCP. Serve Node.js 20 o successivo (`node -v`). Non e richiesta una chiave OpenAI API.

### Autenticazione OAuth (Auth0)

1. Crea un tenant Auth0 e una API con identifier esattamente `https://calendar.tommasogiovannoni.com/mcp`, firma RS256.
2. Definisci i permessi `calendar:read` e `calendar:write`, abilita RBAC e includi i permessi nel token. Assegnali soltanto al tuo utente.
3. Configura la registrazione del client ChatGPT tramite CIMD o un client OAuth predefinito seguendo la [guida OpenAI per Auth0](https://github.com/openai/openai-mcpkit/blob/main/python-authenticated-mcp-server-scaffold/README.md#2-configure-auth0-authentication). Il provider deve supportare authorization code + PKCE S256 e rilasciare un token con `aud` pari all'identifier della API.
4. Copia l'issuer Auth0 (con `/` finale) e il valore `sub` del tuo utente dal token/profilo Auth0. Non usare l'email al posto di `sub`.
5. Registra in Auth0 la redirect URI esatta indicata da ChatGPT quando crei la connessione. Non indovinarla: dipende dalla modalita di registrazione del client.

### Segreti e servizio sulla VPS

Genera un segreto locale una sola volta e inseriscilo sia nel `.env` Laravel (`MCP_BRIDGE_TOKEN`) sia in `/etc/calendar-mcp.env` (`MCP_BRIDGE_TOKEN`):

```bash
cd /var/www/calendar
openssl rand -hex 32
nano .env
sudo cp mcp/env.example /etc/calendar-mcp.env
sudo nano /etc/calendar-mcp.env
sudo chown root:root /etc/calendar-mcp.env
sudo chmod 600 /etc/calendar-mcp.env
php artisan config:clear
php artisan config:cache
```

Compila `/etc/calendar-mcp.env` con `MCP_OAUTH_ISSUER`, `MCP_ALLOWED_SUB`, `MCP_PUBLIC_URL`, lo stesso `MCP_BRIDGE_TOKEN` e i valori locali del file di esempio. Verifica il percorso di Node con `command -v node` e aggiorna `ExecStart` nel servizio se diverso da `/usr/bin/node`:

```bash
sudo cp mcp/calendar-mcp.service.example /etc/systemd/system/calendar-mcp.service
sudo systemctl daemon-reload
sudo systemctl enable --now calendar-mcp
sudo systemctl status calendar-mcp --no-pager
```

### Nginx e test

Inserisci il contenuto di `mcp/nginx.conf.example` nel server block HTTPS esistente del calendario. Non sostituire il blocco Laravel esistente. Installa inoltre `mcp/nginx-bridge.conf.example` come server Nginx separato, ascoltando solo su `127.0.0.1:3002` (adatta il socket PHP-FPM se necessario):

```bash
sudo cp mcp/nginx-bridge.conf.example /etc/nginx/sites-available/calendar-mcp-bridge
sudo ln -s /etc/nginx/sites-available/calendar-mcp-bridge /etc/nginx/sites-enabled/calendar-mcp-bridge
sudo nginx -t
sudo systemctl reload nginx
curl -i https://calendar.tommasogiovannoni.com/mcp
curl -s https://calendar.tommasogiovannoni.com/.well-known/oauth-protected-resource/mcp
curl -i https://calendar.tommasogiovannoni.com/internal/mcp/projects
curl -i http://127.0.0.1:3002/internal/mcp/projects
journalctl -u calendar-mcp -n 100 --no-pager
```

Risposte attese: `/mcp` senza token restituisce `401` con `WWW-Authenticate`; il metadata endpoint restituisce `resource`, `authorization_servers` e gli scope; il bridge pubblico restituisce `404`, quello locale senza token `403`, mai dati. Prova poi con MCP Inspector e collega l'URL `https://calendar.tommasogiovannoni.com/mcp` in ChatGPT (Impostazioni > Sicurezza e accesso > Modalita sviluppatore > Plugins). Autorizza soltanto il tuo utente e verifica prima `list_projects`, poi `create_task`, `update_task` e `complete_task`.

Ad ogni aggiornamento di questa integrazione: esegui i comandi generali della sezione 2 e infine `sudo systemctl restart calendar-mcp`.
