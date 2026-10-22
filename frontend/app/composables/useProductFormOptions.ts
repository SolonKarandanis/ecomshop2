import type { Brand } from '~/types/catalogue'
import type { ProductAttribute } from '~/types/supplierProduct'

/** Active brands, for the Supplier product form. */
export function useBrands() {
  return useCatalogFetch<{ data: Brand[] }, Brand[]>('/brands', {
    key: 'brands',
    transform: response => response.data,
  })
}

/** Product attributes with their options, for the Supplier product form. */
export function useProductAttributes() {
  return useCatalogFetch<{ data: ProductAttribute[] }, ProductAttribute[]>('/attributes', {
    key: 'product-attributes',
    transform: response => response.data,
  })
}
