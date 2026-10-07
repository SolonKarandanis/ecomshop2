export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()

  if (!auth.resolved) {
    // An unreachable API is treated as "not known to be logged in".
    await auth.fetchUser().catch(() => null)
  }

  if (auth.isAuthenticated) {
    return navigateTo('/')
  }
})
