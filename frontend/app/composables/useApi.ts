const XSRF_COOKIE = 'XSRF-TOKEN'
const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS']

// ofetch retries a failed GET once, straight away. That's kept for a dropped
// connection or a 5xx, but not for a 429: the API has just said to wait (the
// Retry-After header), and an instant retry only adds to the load it shed.
export const RETRY_STATUS_CODES = [408, 409, 425, 500, 502, 503, 504]

function readCookie(name: string): string | null {
  if (import.meta.server) {
    return null
  }

  const match = document.cookie
    .split('; ')
    .find(cookie => cookie.startsWith(`${name}=`))

  return match ? decodeURIComponent(match.slice(name.length + 1)) : null
}

/**
 * `$fetch` bound to the Laravel API (`${apiBase}/api`, ADR-0003) with the
 * Sanctum SPA cookie flow built in: credentials are always sent, and before a
 * state-changing request the CSRF cookie is primed (if the browser doesn't
 * already hold one) and echoed back as `X-XSRF-TOKEN`. Every request asks for
 * the page's language, so API messages come back translated (#78).
 */
export function useApi() {
  const origin = useRuntimeConfig().public.apiBase
  // Captured now: on the server the Nuxt context isn't available inside $fetch hooks.
  const nuxtApp = tryUseNuxtApp()

  return $fetch.create({
    baseURL: `${origin}/api`,
    credentials: 'include',
    headers: { Accept: 'application/json' },
    retryStatusCodes: RETRY_STATUS_CODES,
    async onRequest({ options }) {
      options.headers.set('Accept-Language', currentLocale(nuxtApp))

      const method = (options.method ?? 'GET').toUpperCase()

      if (SAFE_METHODS.includes(method)) {
        return
      }

      if (!readCookie(XSRF_COOKIE)) {
        await $fetch('/sanctum/csrf-cookie', { baseURL: origin, credentials: 'include' })
      }

      const token = readCookie(XSRF_COOKIE)

      if (token) {
        options.headers.set('X-XSRF-TOKEN', token)
      }
    },
  })
}
