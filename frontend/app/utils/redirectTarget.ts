import type { LocationQueryValue } from 'vue-router'

/**
 * The post-login destination from a `?redirect=` query param, restricted to
 * same-app paths so the login page can't be used as an open redirect.
 * Without one it's `fallback`, the home page in the current language.
 */
export function redirectTarget(value: LocationQueryValue | LocationQueryValue[] | undefined, fallback = '/'): string {
  const path = Array.isArray(value) ? value[0] : value

  if (!path || !path.startsWith('/') || path.startsWith('//') || path.startsWith('/\\')) {
    return fallback
  }

  return path
}
