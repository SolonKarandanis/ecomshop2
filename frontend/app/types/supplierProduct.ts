import type { Brand, Category } from '~/types/catalogue'

// Mirror the backend's App\Data\{SupplierProductData,ProductImageData,
// ProductAttributeValueData,AttributeData,AttributeOptionData}.

export interface ProductImage {
  id: number
  url: string
  thumbnail: string
  small: string
  large: string
}

export interface ProductAttributeValue {
  id: number
  attribute_id: number
  attribute_option_id: number
}

export interface SupplierProduct {
  id: number
  name: string
  slug: string
  description: string | null
  price: number
  category_id: number
  brand_id: number
  is_active: boolean
  is_featured: boolean
  in_stock: boolean
  on_sale: boolean
  category?: Category | null
  brand?: Brand | null
  attributes?: ProductAttributeValue[]
  images?: ProductImage[]
}

export interface AttributeOption {
  id: number
  // A translation key such as "attribute.option.color.silver"; `label` is its translation.
  name: string
  label: string
}

export interface ProductAttribute {
  id: number
  name: string
  label: string
  type: string
  options?: AttributeOption[]
}

// POST/PATCH /api/supplier-products. `slug` may be left empty on create: the API
// generates one from `name`. `supplier_id` and `is_featured` are never sent.
export interface SupplierProductPayload {
  name: string
  slug: string
  description: string
  price: number | undefined
  category_id: number | undefined
  brand_id: number | undefined
  is_active: boolean
  in_stock: boolean
  on_sale: boolean
  attributes: { attribute_id: number | undefined, attribute_option_id: number | undefined }[]
}
