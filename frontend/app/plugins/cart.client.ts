// Load the cart once on startup so the header badge is right on every page,
// not just /cart. Not awaited, like the session check in auth.client.ts.
export default defineNuxtPlugin(() => {
  useCartStore().fetchCart().catch(() => null)
})
