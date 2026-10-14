import type { LocationQuery } from 'vue-router'

export const SORT_OPTIONS = [
  { label: 'Newest', value: 'latest' },
  { label: 'Price: low to high', value: 'price_asc' },
  { label: 'Price: high to low', value: 'price_desc' },
  { label: 'Top rated', value: 'rating' },
] as const

export type ProductSort = typeof SORT_OPTIONS[number]['value']

export interface ProductFilters {
  q: string
  sort: ProductSort
  categories: number[]
  page: number
}

export const DEFAULT_FILTERS: ProductFilters = { q: '', sort: 'latest', categories: [], page: 1 }

function first(value: LocationQuery[string] | undefined): string {
  const item = Array.isArray(value) ? value[0] : value
  return item ?? ''
}

function toPositiveInt(value: string | null | undefined): number | null {
  const n = Number(value)
  return Number.isInteger(n) && n > 0 ? n : null
}

/** Reads `/products` filters from the URL, dropping anything the API would reject. */
export function filtersFromQuery(query: LocationQuery): ProductFilters {
  const sort = first(query.sort)
  const rawCategories = query['categories[]']
  const categories = (Array.isArray(rawCategories) ? rawCategories : [rawCategories])
    .map(toPositiveInt)
    .filter((id): id is number => id !== null)

  return {
    q: first(query.q).trim(),
    sort: SORT_OPTIONS.some(option => option.value === sort) ? sort as ProductSort : DEFAULT_FILTERS.sort,
    categories,
    page: toPositiveInt(first(query.page)) ?? DEFAULT_FILTERS.page,
  }
}

/**
 * The query string for both the page URL and `GET /api/products`, in the form
 * Laravel parses: arrays as `categories[]=1&categories[]=2` (a bare repeated
 * `categories=` key would collapse to its last value). Defaults are omitted to
 * keep URLs clean.
 */
export function queryFromFilters(filters: ProductFilters): Record<string, string | string[]> {
  const query: Record<string, string | string[]> = {}

  if (filters.q) query.q = filters.q
  if (filters.sort !== DEFAULT_FILTERS.sort) query.sort = filters.sort
  if (filters.categories.length) query['categories[]'] = filters.categories.map(String)
  if (filters.page !== DEFAULT_FILTERS.page) query.page = String(filters.page)

  return query
}
