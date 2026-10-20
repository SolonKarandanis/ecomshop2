import type { Cart, CartItem, CartResponse } from '~/types/cart'

/**
 * The cart, for guests (cookie-backed on the API) and Buyers (database-backed)
 * alike. Every cart endpoint returns the whole cart, so each action replaces
 * the state from the response instead of patching it. Errors (e.g. the 400
 * for a product from a second Supplier) are left for the caller to show.
 */
export const useCartStore = defineStore('cart', () => {
  const items = ref<CartItem[]>([])
  const totalPrice = ref(0)
  // Whether the cart has come back from the API yet, so /cart doesn't flash "empty" first.
  const loaded = ref(false)

  const itemCount = computed(() => items.value.reduce((sum, item) => sum + item.quantity, 0))
  // The API's total, not recalculated here: it already accounts for attribute pricing.
  const subtotal = computed(() => totalPrice.value)

  function setCart(cart: Cart) {
    items.value = cart.items
    totalPrice.value = cart.total_price
    loaded.value = true
  }

  async function request(path: string, options: Parameters<ReturnType<typeof useApi>>[1] = {}) {
    const response = await useApi()<CartResponse>(path, options)
    setCart(response.data)
    return response.data
  }

  async function fetchCart() {
    const cart = await request('/cart')
    if (cart.notice) {
      useToast().add({ title: cart.notice, color: 'warning' })
    }
  }

  async function addItem(productId: number, quantity = 1) {
    await request('/cart/items', { method: 'POST', body: { product_id: productId, quantity } })
  }

  async function updateItem(cartItemId: string, quantity: number) {
    await request(`/cart/items/${cartItemId}`, { method: 'PATCH', body: { quantity } })
  }

  async function removeItem(cartItemId: string) {
    await request(`/cart/items/${cartItemId}`, { method: 'DELETE' })
  }

  function reset() {
    items.value = []
    totalPrice.value = 0
  }

  return { items, totalPrice, loaded, itemCount, subtotal, fetchCart, addItem, updateItem, removeItem, reset }
})
