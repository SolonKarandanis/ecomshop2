export function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleDateString('en-IE', { dateStyle: 'medium', timeZone: 'UTC' }) : ''
}

export function formatDateTime(value: string | null): string {
  return value ? new Date(value).toLocaleString('en-IE', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' }) : ''
}
