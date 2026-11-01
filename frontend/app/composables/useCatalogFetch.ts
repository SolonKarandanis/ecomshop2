import type { UseFetchOptions } from 'nuxt/app'

/**
 * `$fetch` for the public catalogue reads. Unlike `useApi()` it never sends
 * credentials, so neither Nuxt's server nor the browser attaches the Sanctum
 * session cookie to these requests (ADR-0002). It asks for the page's
 * language, in SSR too, so labels come back translated (#78).
 */
export function useCatalogApi() {
  // Captured now: on the server the Nuxt context isn't available inside $fetch hooks.
  const nuxtApp = tryUseNuxtApp()

  return $fetch.create({
    baseURL: `${useRuntimeConfig().public.apiBase}/api`,
    headers: { Accept: 'application/json' },
    onRequest({ options }) {
      options.headers.set('Accept-Language', currentLocale(nuxtApp))
    },
  })
}

/** `useFetch` over `useCatalogApi()`: SSR-rendered and hydrated without refetching. */
export function useCatalogFetch<ResT, DataT = ResT>(url: string | (() => string), options: UseFetchOptions<ResT, DataT> = {}) {
  return useFetch(url, { ...options, $fetch: useCatalogApi() })
}
