// Use after `auth`: keeps non-Suppliers out of /supplier/**. A UI convenience
// only; the API's `supplier-action` gate (403) is the real authority.
export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()

  if (!auth.resolved) {
    await auth.fetchUser().catch(() => null)
  }

  if (!auth.isSupplier) {
    return navigateTo('/')
  }
})
