/**
 * A URL slug from a product name, close to Laravel's `Str::slug()`: accents
 * dropped, lower-case, and runs of anything else collapsed into one dash.
 */
export function slugify(value: string): string {
  return value
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/@/g, ' at ')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}
