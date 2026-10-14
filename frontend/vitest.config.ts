import { defineVitestConfig } from '@nuxt/test-utils/config'

export default defineVitestConfig({
  test: {
    environment: 'nuxt',
    include: ['tests/unit/**/*.{test,spec}.ts'],
    passWithNoTests: true,
    environmentOptions: {
      nuxt: {
        // An empty API origin keeps requests relative, so tests serve them with
        // registerEndpoint instead of reaching a real backend.
        overrides: { runtimeConfig: { public: { apiBase: '' } } },
      },
    },
  },
})
