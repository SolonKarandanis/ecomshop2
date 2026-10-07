const XSRF_COOKIE = 'XSRF-TOKEN'
const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS']

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
 * already hold one) and echoed back as `X-XSRF-TOKEN`.
 */
export function useApi() {
  const origin = useRuntimeConfig().public.apiBase

  return $fetch.create({
    baseURL: `${origin}/api`,
    credentials: 'include',
    headers: { Accept: 'application/json' },
    async onRequest({ options }) {
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
