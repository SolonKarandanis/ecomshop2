// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  modules: ['@nuxt/ui', '@pinia/nuxt', '@nuxt/eslint'],

  devtools: { enabled: true },

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    public: {
      // Backend origin; the API itself lives under `${apiBase}/api` (ADR-0003),
      // while `/sanctum/csrf-cookie` stays at the origin root.
      apiBase: 'http://localhost:8000',
    },
  },

  // ADR-0002: public catalogue routes render with SSR; anything that needs the
  // Sanctum session is CSR-only so Nuxt's server never forwards the cookie.
  routeRules: {
    '/cart/**': { ssr: false },
    '/checkout/**': { ssr: false },
    '/success': { ssr: false },
    '/cancel': { ssr: false },
    '/orders/**': { ssr: false },
    '/profile/**': { ssr: false },
    '/supplier/**': { ssr: false },
    '/notifications/**': { ssr: false },
  },

  compatibilityDate: '2025-07-15',
})
