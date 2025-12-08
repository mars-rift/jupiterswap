# Static swap page & Proxy Usage

This document explains the static `swap.html` and PHP proxy (`api-proxy.php`) included in the `public/` folder. The goal is to enable static hosting for the Jupiter swap plugin and an optional server-side proxy for the Ultra/Lite APIs.

## Files
- `swap.html` — a static, embeddable page that loads the compiled plugin JS and CSS from `public/` and calls `window.Jupiter.init()` with runtime props.
- `swap.css` — theme overrides (gold/black/forest green) and some shadow/Shadow-DOM friendly tweaks.
- `swap.php` — a simple PHP wrapper for injecting server-side init props and runtime theme variables into the page (optional).
- `api-proxy.php` — a PHP proxy that forwards selected requests to `ultra-api.jup.ag` / `lite-api.jup.ag`. This allows the host to implement server-side secrets, simple rate limiting, or logging.

## Using `swap.html`
1. Copy `public/*` files to your static host (S3, Netlify, GitHub Pages, or a simple Apache/Nginx server).
2. The page reads query parameters for dynamic configuration:
   - `primary` — primary theme color hex code (#012A1A)
   - `interactive` — interactive color hex code (#D4AF37)
   - `background` — background color hex code (#000000)
   - `mode` — display mode (`embedded` or `standalone`)
   - `integratedTargetId` — DOM id to integrate widget into
   - `widgetPosition` — `bottom-left` / `bottom-right` / `top-left` / `top-right`
   - `widgetSize` — `md`, `lg`, `xl`
   - `enableWalletPassthrough` — `true` / `false`

Example:
```
https://your-host.example/swap.html?primary=%23011812&interactive=%23D4AF37&background=%23000000&mode=embedded
```

## Using `swap.php`
If you need to inject server-side computed props or protect secrets, use `swap.php`:

1. Place `swap.html` and `swap.css` alongside `swap.php` on a PHP-capable host (Apache with PHP, PHP-FPM, etc.).
2. Use query parameters (same as `swap.html`) or server-side config to render the page.

Note: `swap.php` is a wrapper to help with server-side injection of runtime props and theming. It is intentionally simple and not environment hardened — use it as a starting point.

## Using `api-proxy.php`
This proxy forwards requests to Jupiter Ultra/Lite endpoints. Use it when you need the server to attach credentials or hide requests from the client.

Example usage:

GET request:
```
GET /api-proxy.php?target=ultra&path=quote&tokenIn=...&tokenOut=...
```

POST request:
```
POST /api-proxy.php?target=ultra&path=execute
Content-Type: application/json

{ ...body... }
```

### Security & Best Practices
- Set the environment variable `PROXY_API_KEY` to your server-side API key if you need the server to attach `Authorization: Bearer` to the outbound request.
- Restrict allowed `target` values (already done in code) and restrict `path` formats.
- Be careful with logging request bodies — don't log secrets.
- Configure rate-limiting and request quotas depending on your host and expected traffic.
- Limit allowed content types and maximum POST body sizes (already limited to 1MB in code).
- Use TLS on the host, enable HSTS and other secure headers.
- If you are deployed behind a reverse proxy (Cloudflare/NGINX), make sure to pass the intended headers properly.

## Example: Forcing plugin to use proxy endpoints
If you want the plugin to use the proxy instead of calling `ultra-api.jup.ag` directly, configure your client or wrapper to rewrite API URLs to `/api-proxy.php?target=ultra&path=...` or use a reverse proxy rule at the web server level to map the Ultra/Lite API hostnames to your proxy path.

## Final notes
This setup is intended as a minimal, deployable example that removes the need for a Node.js server and uses PHP only if server-side features are required. Adjust and harden it further before deploying to production.
