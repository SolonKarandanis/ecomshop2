// Prices are EUR across the backend (orders, exports, Filament widgets);
// only the number format follows the page's language.
const formatters = new Map<string, Intl.NumberFormat>()

export function formatPrice(price: number, language: string = currentLanguage()): string {
  let formatter = formatters.get(language)

  if (!formatter) {
    formatter = new Intl.NumberFormat(language, { style: 'currency', currency: 'EUR' })
    formatters.set(language, formatter)
  }

  return formatter.format(price)
}
