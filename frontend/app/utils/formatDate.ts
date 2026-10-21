export function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleDateString('en-IE', { dateStyle: 'medium', timeZone: 'UTC' }) : ''
}
