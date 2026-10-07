export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()

  if (!auth.resolved) {
    // An unreachable API is treated as "not known to be logged in".
    await auth.fetchUser().catch(() => null)
  }

  if (!auth.isAuthenticated) {
    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }
})
