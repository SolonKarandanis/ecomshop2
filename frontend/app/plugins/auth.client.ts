// Resolve the session once on startup so the header reflects it on every page,
// including SSR-rendered ones (where the server never sees the session cookie,
// per ADR-0002). Not awaited: rendering shouldn't wait on the API.
export default defineNuxtPlugin(() => {
  useAuthStore().fetchUser().catch(() => null)
})
