// https://nuxt.com/docs/api/configuration/nuxt-config

// English has no URL prefix; Greek and Turkish pages live under /el and /tr (#77).
const LOCALE_PREFIXES = ['', '/el', '/tr']

// ADR-0002: anything that needs the Sanctum session is CSR-only so Nuxt's server
// never forwards the cookie. Each path gets a rule in every language, generated
// from this one list so the prefixed copies can't drift.
const SESSION_ROUTES = [
  '/cart/**',
  '/checkout/**',
  '/success',
  '/cancel',
  '/orders/**',
  '/profile/**',
  '/supplier/**',
  '/notifications/**',
  '/login',
  '/register',
  '/forgot-password',
  '/reset-password',
]

export default defineNuxtConfig({
  modules: ['@nuxt/ui', '@pinia/nuxt', '@nuxt/eslint', '@nuxtjs/i18n'],

  devtools: { enabled: true },

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    public: {
      // Backend origin; the API itself lives under `${apiBase}/api` (ADR-0003),
      // while `/sanctum/csrf-cookie` stays at the origin root.
      apiBase: 'http://localhost:8000',
    },
  },

  // ADR-0002: public catalogue routes render with SSR, in every language.
  routeRules: Object.fromEntries(
    LOCALE_PREFIXES.flatMap(prefix => SESSION_ROUTES.map(route => [`${prefix}${route}`, { ssr: false }])),
  ),

  i18n: {
    locales: [
      { code: 'en', language: 'en-IE', name: 'English', file: 'en.json' },
      { code: 'el', language: 'el-GR', name: 'Ελληνικά', file: 'el.json' },
      { code: 'tr', language: 'tr-TR', name: 'Türkçe', file: 'tr.json' },
    ],
    defaultLocale: 'en',
    strategy: 'prefix_except_default',
    // No automatic redirect by browser language: the URL alone decides, so
    // search engines and shared links always get the language in the address.
    detectBrowserLanguage: false,
    // The storefront's own origin, for absolute hreflang and og:url links.
    // Overridden in production by NUXT_PUBLIC_I18N_BASE_URL.
    baseUrl: 'http://localhost:3000',
  },

  compatibilityDate: '2025-07-15',
})
