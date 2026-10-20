import { beforeEach, describe, expect, it } from 'vitest'
import { registerEndpoint } from '@nuxt/test-utils/runtime'
import { readBody, setResponseStatus } from 'h3'
import { createPinia, setActivePinia } from 'pinia'
import { useCartStore } from '~/stores/cart'
import type { Cart, CartItem } from '~/types/cart'

const SUPPLIER_MISMATCH = 'Your cart can only contain products from one supplier at a time. Clear your cart to add this product.'

// product id → [supplier id, unit price]
const products: Record<number, [number, number]> = { 1: [10, 20], 2: [10, 5], 3: [11, 9] }

let items: CartItem[]
let notice: string | null
let nextId: number

function cart(): { data: Cart } {
  const total = items.reduce((sum, item) => sum + item.total_price, 0)
  return { data: { total_price: total, items, notice } }
}

function item(productId: number, quantity: number): CartItem {
  const price = products[productId]![1]
  return {
    id: String(nextId++),
    product_id: productId,
    product_name: `Product ${productId}`,
    product_slug: `product-${productId}`,
    quantity,
    unit_price: price,
    total_price: price * quantity,
    attributes: {},
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  items = []
  notice = null
  nextId = 1
  document.cookie = 'XSRF-TOKEN=token; path=/'
  useToast().clear()

  registerEndpoint('/api/cart', () => {
    const response = cart()
    notice = null
    return response
  })
  registerEndpoint('/api/cart/items', {
    method: 'POST',
    handler: async (event) => {
      const { product_id, quantity } = await readBody(event)
      const supplier = products[product_id]![0]
      if (items.some(i => products[i.product_id]![0] !== supplier)) {
        setResponseStatus(event, 400)
        return { message: SUPPLIER_MISMATCH }
      }
      items = [...items, item(product_id, quantity)]
      return cart()
    },
  })
  // registerEndpoint matches exact paths only, so register each item id this suite uses.
  for (const id of ['1', '2']) {
    registerEndpoint(`/api/cart/items/${id}`, async (event) => {
      if (event.method === 'DELETE') {
        items = items.filter(i => i.id !== id)
      }
      else {
        const { quantity } = await readBody(event)
        items = items.map(i => i.id === id ? { ...i, quantity, total_price: i.unit_price * quantity } : i)
      }
      return cart()
    })
  }
})

describe('cart store', () => {
  it('fetches the cart, counting quantities and taking the subtotal from the API', async () => {
    items = [item(1, 2), item(2, 3)]
    const store = useCartStore()

    expect(store.loaded).toBe(false)
    await store.fetchCart()

    expect(store.loaded).toBe(true)
    expect(store.items).toHaveLength(2)
    expect(store.itemCount).toBe(5)
    expect(store.subtotal).toBe(55)
  })

  it('adds an item and replaces the state from the response', async () => {
    const store = useCartStore()

    await store.addItem(1, 2)

    expect(store.itemCount).toBe(2)
    expect(store.subtotal).toBe(40)
  })

  it('surfaces the API rejection of a second Supplier and keeps the cart as it was', async () => {
    const store = useCartStore()
    await store.addItem(1)

    // Rejected, not swallowed, so the caller can show the API's message.
    const error = await store.addItem(3).catch((e: unknown) => e)
    expect(apiStatus(error)).toBe(400)
    expect(apiErrorMessage(error)).toBe(SUPPLIER_MISMATCH)
    expect(store.items.map(i => i.product_id)).toEqual([1])
  })

  it('updates an item quantity', async () => {
    const store = useCartStore()
    await store.addItem(1)

    await store.updateItem(store.items[0]!.id, 4)

    expect(store.itemCount).toBe(4)
    expect(store.subtotal).toBe(80)
  })

  it('removes an item', async () => {
    const store = useCartStore()
    await store.addItem(1)
    await store.addItem(2)

    await store.removeItem(store.items[0]!.id)

    expect(store.items.map(i => i.product_id)).toEqual([2])
  })

  it('shows the API notice as a toast, once', async () => {
    const message = 'Your previously saved cart was from a different supplier and has been replaced.'
    notice = message
    const store = useCartStore()

    await store.fetchCart()
    await store.fetchCart()

    expect(useToast().toasts.value.map(t => t.title)).toEqual([message])
  })

  it('resets to an empty cart', async () => {
    items = [item(1, 2)]
    const store = useCartStore()
    await store.fetchCart()

    store.reset()

    expect(store.items).toEqual([])
    expect(store.itemCount).toBe(0)
    expect(store.subtotal).toBe(0)
  })
})
