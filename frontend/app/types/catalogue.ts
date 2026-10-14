// Mirror the backend's App\Data\{ProductData,CategoryData,BrandData,ReviewData}.

export interface Category {
  id: number
  name: string
  slug: string
  thumbnail: string
}

export interface Brand {
  id: number
  name: string
  slug: string
}

export interface Product {
  id: number
  name: string
  slug: string
  description: string | null
  price: number
  is_featured: boolean
  in_stock: boolean
  on_sale: boolean
  average_rating: number | null
  reviews_count: number
  thumbnail: string
  image: string
  category?: Category | null
  brand?: Brand | null
}

export interface Review {
  id: number
  product_id: number
  user_id: number
  rating: number
  comment: string | null
  status: string
  admin_reply: string | null
  created_at: string | null
}

// Spatie's PaginatedDataCollection shape (`links` omitted: we page by number).
export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// GET /api/products/{slug}: the product, plus its first page of published reviews.
export interface ProductDetailResponse {
  data: Product
  reviews: Paginated<Review>
}
