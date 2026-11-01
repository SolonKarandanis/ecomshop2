import type { Locale } from 'vue-i18n'

/**
 * Moving between languages: the header switcher, and logging in as a user
 * whose saved language differs from the page's. Slugs are the same in every
 * language, so the same page always exists in the other one.
 */
export function useLocaleSwitch() {
  const { $i18n } = useNuxtApp()
  const localePath = useLocalePath()
  const switchLocalePath = useSwitchLocalePath()
  const routeBaseName = useRouteBaseName()
  const router = useRouter()
  const auth = useAuthStore()

  /** `path` (in any language) as the same page in `code`. */
  function pathInLocale(path: string, code: Locale): string {
    const route = router.resolve(path)
    const name = routeBaseName(route)

    if (typeof name !== 'string') {
      return localePath(path, code)
    }

    return localePath({ name, params: route.params, query: route.query, hash: route.hash }, code)
  }

  /** Goes to the current page in `code`, and saves it as a logged-in user's language. */
  async function switchTo(code: Locale) {
    // Saving is a convenience for their emails; a failure mustn't block the switch.
    auth.saveLocale(code).catch(() => null)
    await navigateTo(switchLocalePath(code))
  }

  /** After login: `path` in the user's saved language, if they have one other than the page's. */
  function pathForUser(path: string): string {
    const saved = auth.user?.locale

    return isLocale(saved) && saved !== $i18n.locale.value ? pathInLocale(path, saved) : path
  }

  return { pathInLocale, switchTo, pathForUser }
}
