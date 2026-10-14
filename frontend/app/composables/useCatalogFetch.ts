import type { UseFetchOptions } from 'nuxt/app'

/**
 * `$fetch` for the public catalogue reads. Unlike `useApi()` it never sends
 * credentials, so neither Nuxt's server nor the browser attaches the Sanctum
 * session cookie to these requests (ADR-0002).
 */
export function useCatalogApi() {
  return $fetch.create({
    baseURL: `${useRuntimeConfig().public.apiBase}/api`,
    headers: { Accept: 'application/json' },
  })
}

/** `useFetch` over `useCatalogApi()`: SSR-rendered and hydrated without refetching. */
export function useCatalogFetch<ResT, DataT = ResT>(url: string | (() => string), options: UseFetchOptions<ResT, DataT> = {}) {
  return useFetch(url, { ...options, $fetch: useCatalogApi() })
}
