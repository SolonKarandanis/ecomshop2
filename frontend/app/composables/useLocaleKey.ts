/**
 * An asyncData cache key that includes the page's language. Content the API
 * translates (product and category names, payment method labels) is cached
 * per language, so switching language fetches it again instead of showing
 * the previous language's copy (#81).
 */
export function useLocaleKey(key: string | (() => string)): () => string {
  const { $i18n } = useNuxtApp()

  return () => `${toValue(key)}:${$i18n.locale.value}`
}
