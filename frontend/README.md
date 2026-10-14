# ecomshop2 frontend

Nuxt 4 storefront for the ecomshop2 Laravel API (see `docs/adr/0001`–`0003` at the repo root).

## Setup

```bash
cp .env.example .env   # NUXT_PUBLIC_API_BASE = Laravel origin, without /api
npm install
npx playwright install chromium   # first time only, for e2e
```

## Scripts

| Command | What it does |
|---|---|
| `npm run dev` | Dev server on http://localhost:3000 |
| `npm run build` | Production build (`.output/server/index.mjs`) |
| `npm run lint` | ESLint (`@nuxt/eslint`) |
| `npm run test` | Vitest unit/component tests (`tests/unit`) |
| `npm run test:e2e` | Playwright e2e tests (`tests/e2e`); starts the dev server itself |

## Rendering

Public catalogue routes are SSR; every route that needs the Sanctum session is
`ssr: false` via `routeRules` in `nuxt.config.ts` (ADR-0002).
