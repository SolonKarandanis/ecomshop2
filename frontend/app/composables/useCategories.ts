import type { Category } from '~/types/catalogue'

/** Active categories, shared under one key so pages reuse the same payload. */
export function useCategories() {
  return useCatalogFetch<{ data: Category[] }, Category[]>('/categories', {
    key: 'categories',
    transform: response => response.data,
  })
}
