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

/**
 * `useFetch` over `useCatalogApi()`: SSR-rendered and hydrated without refetching.
 * The cache key includes the language, so switching language fetches that
 * language's content rather than reusing another's (#81).
 */
export function useCatalogFetch<ResT, DataT = ResT>(url: string | (() => string), options: UseFetchOptions<ResT, DataT> = {}) {
  // Without an explicit key, Nuxt's own key is built from the URL and query.
  const baseKey = () => options.key
    ? toValue(options.key)
    : JSON.stringify([toValue(url), unwrapParams(options.query), unwrapParams(options.params)])

  return useFetch(url, { ...options, key: useLocaleKey(baseKey), $fetch: useCatalogApi() })
}

// A query or params object as plain values: it may be a ref, and may hold refs
// itself, as useFetch allows.
function unwrapParams(params: unknown): Record<string, unknown> | undefined {
  const value = toValue(params)

  return value && typeof value === 'object'
    ? Object.fromEntries(Object.entries(value).map(([name, item]) => [name, toValue(item)]))
    : undefined
}
