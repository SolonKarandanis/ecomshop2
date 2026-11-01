// Dates are shown in UTC, in the page's language.
export function formatDate(value: string | null, language: string = currentLanguage()): string {
  return value ? new Date(value).toLocaleDateString(language, { dateStyle: 'medium', timeZone: 'UTC' }) : ''
}

export function formatDateTime(value: string | null, language: string = currentLanguage()): string {
  return value ? new Date(value).toLocaleString(language, { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' }) : ''
}
