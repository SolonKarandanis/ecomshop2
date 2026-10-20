// Mirrors the backend's App\Data\{CartData,CartItemData}, as returned (wrapped
// in `data`) by every /api/cart endpoint.

export interface CartItem {
  // A database id for a logged-in Buyer, a cookie-generated uuid for a guest.
  id: string
  product_id: number
  product_name: string | null
  product_slug: string | null
  quantity: number
  unit_price: number
  total_price: number
  // attribute id → chosen option id
  attributes: Record<string, number>
}

export interface Cart {
  total_price: number
  items: CartItem[]
  // One-time message, e.g. that the saved cart was replaced at login. Only GET /api/cart sets it.
  notice?: string | null
}

export interface CartResponse {
  data: Cart
}
