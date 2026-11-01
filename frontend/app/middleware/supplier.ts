// Use after `auth`: keeps non-Suppliers out of /supplier/**. A UI convenience
// only; the API's `supplier-action` gate (403) is the real authority.
export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()
  const localePath = useLocalePath()

  if (!auth.resolved) {
    await auth.fetchUser().catch(() => null)
  }

  if (!auth.isSupplier) {
    return navigateTo(localePath('/', routeLocale(to)))
  }
})
