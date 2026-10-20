# Realtime relay (WebSocket) for jitsi_life — setup

Switches the instant-lesson pages from "ask the server every few seconds" to "the server pushes a signal the moment
something changes". Optional: without it everything works on polling.

## How it fits together

```
 Browser (js/instant.js) ──Socket.IO (wss)──► nginx /jitsi_life/notify-ws/ ──► relay container (Node, :3100)
        ▲                                                                          ▲
        │ fetch instant.php (state)                                                │ POST /emit {userid, notification}
        │                                                                          │ header x-internal-key
 Moodle (PHP, jitsi_life_app) ─────────────── docker network saas_net ────────────┘
```

- PHP handles one request and exits — it cannot keep a socket open. The relay is a tiny always-on Node process
  (Express + Socket.IO) that holds the browsers' sockets.
- When a request changes state, PHP (`local_nit_lessons\local\realtime`) does a 2-second-max `POST /emit` to the
  relay: "tell user 42: {type: instant, what: offer, requestid: 17}". The relay emits it into room `user:42`.
- The browser joins room `user:<id>` with a token the page gets from PHP: `<userid>.<expiry>.<HMAC-SHA256>`, signed
  with a secret only PHP and the relay know (no DB lookup on the relay).
- Signals carry no data; the page then calls `instant.php` as usual. If the relay is down, polling still works.

## 1. Build and run the relay (on the server)

```bash
mkdir -p /var/www/html/jitsi_life/notify-ws && cd /var/www/html/jitsi_life/notify-ws
```
Copy the three files from this repo's `server/notify-ws/` (`Dockerfile`, `package.json`, `server.js` — the same relay
as the academy site), e.g. from your PC:
```bash
scp server/notify-ws/* root@<server>:/var/www/html/jitsi_life/notify-ws/
```

Add a service to `/var/www/html/jitsi_life/docker-compose.yml`:
```yaml
  notify:
    build: ./notify-ws
    container_name: jitsi_life_notify
    restart: unless-stopped
    ports: ["127.0.0.1:3101:3100"]
    environment:
      - PORT=3100
      - NOTIFY_SECRET=<long random secret>
      - INTERNAL_KEY=<same secret>
      - SOCKET_PATH=/jitsi_life/notify-ws/socket.io
      - ALLOWED_ORIGIN=https://academy2026.nitg-eg.com
    networks: [saas_net]
```
Make a secret: `openssl rand -hex 32`. Then:
```bash
cd /var/www/html/jitsi_life && docker compose up -d --build notify
curl -s http://127.0.0.1:3101/health          # → {"status":"ok"}
```

## 2. Reverse proxy (host nginx, the server block of academy2026.nitg-eg.com)

Put this **before / next to** the existing `location /jitsi_life/` (nginx picks the longest matching prefix):
```nginx
location /jitsi_life/notify-ws/ {
    proxy_pass http://127.0.0.1:3101;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 3600s;
}
```
```bash
nginx -t && systemctl reload nginx
curl -s "https://academy2026.nitg-eg.com/jitsi_life/notify-ws/socket.io/?EIO=4&transport=polling"   # → 0{"sid":...
```

## 3. Tell Moodle (`/var/www/html/jitsi_life/config.php`, before `require_once`)

```php
$CFG->academy_notify_publicurl   = 'https://academy2026.nitg-eg.com';
$CFG->academy_notify_path        = '/jitsi_life/notify-ws/socket.io';
$CFG->academy_notify_internalurl = 'http://jitsi_life_notify:3100';
$CFG->academy_notify_secret      = '<same secret>';
```
```bash
docker exec jitsi_life_app curl -s http://jitsi_life_notify:3100/health   # → {"status":"ok"}
docker exec -u 33 jitsi_life_app php /var/www/html/admin/cli/purge_caches.php
```
(If `curl` is missing in the app image: `docker exec jitsi_life_app php -r 'echo file_get_contents("http://jitsi_life_notify:3100/health");'`.)

## 4. Verify in the browser

1. Teacher page (Student requests, "Available now" on) → DevTools **Console**: `NIT_INSTANT.ws` shows `{url, path, token}`
   (null = config not loaded).
2. DevTools **Network → WS**: a `socket.io/?EIO=4&transport=websocket` connection with status **101**.
3. Send a request from a student: in that connection's **Messages** a frame with `"type":"instant","what":"offer"`
   arrives at once, and the pop-up shows immediately.
4. `docker stop jitsi_life_notify` → it still works (slower, by polling). `docker start jitsi_life_notify` again.

## Troubleshooting

| Symptom | Cause |
|---|---|
| `NIT_INSTANT.ws` is null | config.php lines missing / caches not purged |
| WS connection 404 | nginx location missing, or `SOCKET_PATH` ≠ `academy_notify_path` |
| WS connects then closes at once | secret differs between relay and config.php (token rejected), or clock skew |
| Signals never arrive | `academy_notify_internalurl` not reachable from the app container (network `saas_net`) |
| CORS error | `ALLOWED_ORIGIN` must be exactly `https://academy2026.nitg-eg.com` |

## Relay source (from the academy repo `notify-socket/`)

`Dockerfile`
```dockerfile
FROM node:20-alpine
WORKDIR /app
COPY package.json ./
RUN npm install --omit=dev
COPY server.js ./
EXPOSE 3100
CMD ["node", "server.js"]
```

`package.json`
```json
{
  "name": "academy-notify-ws",
  "version": "1.0.0",
  "private": true,
  "main": "server.js",
  "scripts": { "start": "node server.js" },
  "dependencies": { "express": "^4.19.2", "socket.io": "^4.7.5" }
}
```

`server.js` — in `server/notify-ws/server.js` (HMAC token check, room `user:<id>`, `POST /emit` with
`x-internal-key`, `GET /health`).
