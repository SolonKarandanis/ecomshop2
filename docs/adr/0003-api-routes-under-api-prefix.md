# API routes live under the `/api` prefix, separating them from the Filament panel and matching Laravel's default CORS paths

The backend serves two kinds of traffic from one Laravel app: the JSON API consumed by the Nuxt frontend, and the Filament admin panel at `/admin` (HTML, session-based, redirects to `/admin/login` for guests). The API was originally registered with `apiPrefix: ''`, so its routes sat at the root (`/products`, `/cart`, …) alongside `/admin`. We now use Laravel's default `api` prefix, so every API route is `/api/...`.

Three things follow from the prefix:

- **CORS works without a published `config/cors.php`.** Laravel's default `cors.paths` is `['api/*', 'sanctum/csrf-cookie']`. With no prefix, no API route matched, so cross-origin requests from the Nuxt origin got no CORS headers.
- **JSON error rendering is scoped by path.** `bootstrap/app.php`'s `withExceptions()` renders JSON for `api/*` requests (or any request that `expectsJson()`). That covers unmatched routes (404) and wrong methods (405), which fail before `AlwaysAcceptJsonMiddleware` runs. Filament and any future web routes keep Laravel's HTML/redirect handling without per-path exclusions.
- **The Stripe redirect routes move to `/api/success` and `/api/cancel`.** `StripeService` and `CashOnDeliveryPaymentHandler` build these with `route('success')` / `route('cancel')`, so no code changes were needed.

The Nuxt frontend's API base URL must include `/api`.

## Considered options

- **Keep `apiPrefix: ''` and exclude `admin/*` from JSON rendering (rejected)**: this works for errors, but every new web route (Horizon, Telescope, webhooks) would need another exclusion. It also leaves CORS broken unless `config/cors.php` is published with `paths => ['*']`, which applies CORS headers to Filament too.
- **Serve the API from a separate subdomain with no path prefix (rejected)**: ADR 0001 already puts Laravel and Filament together on `api.<domain>`. Splitting the host again would need more deployment and session-domain config and would buy nothing over a path prefix.
