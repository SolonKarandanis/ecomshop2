import type { NuxtApp } from '#app'
import type { Locale } from 'vue-i18n'

const DEFAULT_LOCALE: Locale = 'en'

// Narrows a value from outside the type system (a route name, the API's
// users.locale, a select's value) to one of the configured locales.
export function isLocale(value: unknown, nuxtApp: NuxtApp | null = tryUseNuxtApp()): value is Locale {
  const codes: readonly string[] = nuxtApp?.$i18n?.localeCodes.value ?? []

  return typeof value === 'string' && codes.includes(value)
}

// The language and region the active locale formats with (en-IE, el-GR, tr-TR),
// and en-IE outside a Nuxt app, such as in plain unit tests.
export function currentLanguage(nuxtApp: NuxtApp | null = tryUseNuxtApp()): string {
  return nuxtApp?.$i18n?.localeProperties.value.language ?? 'en-IE'
}

// The active locale code (en, el, tr), sent to the API as Accept-Language (#78).
export function currentLocale(nuxtApp: NuxtApp | null = tryUseNuxtApp()): Locale {
  return nuxtApp?.$i18n?.locale.value ?? DEFAULT_LOCALE
}

// The locale of a route: @nuxtjs/i18n names each localised route `<name>___<locale>`.
// Middleware redirects use it, since they run before the navigation lands.
export function routeLocale(route: { name?: unknown }, fallback: Locale = DEFAULT_LOCALE): Locale {
  const [, locale] = String(route.name ?? '').split('___')

  return isLocale(locale) ? locale : fallback
}
