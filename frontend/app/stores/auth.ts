import type { LoginPayload, RegisterPayload, User } from '~/types/auth'

// The backend's RolesEnum::ROLE_BUYER value.
export const ROLE_BUYER = 'role.buyer'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  // Whether we've asked the API who the user is yet, so a logged-out visitor
  // doesn't trigger a `/user` request on every navigation.
  const resolved = ref(false)
  let pendingFetch: Promise<User | null> | null = null

  const isAuthenticated = computed(() => user.value !== null)
  const isBuyer = computed(() => user.value?.roles?.includes(ROLE_BUYER) ?? false)

  function setUser(value: User | null) {
    user.value = value
    resolved.value = true
  }

  async function fetchUser(): Promise<User | null> {
    pendingFetch ??= useApi()<{ data: User }>('/user')
      .then(response => response.data)
      .catch((error: unknown) => {
        if (apiStatus(error) === 401) {
          return null
        }
        throw error
      })
      .then((value) => {
        setUser(value)
        return value
      })
      .finally(() => {
        pendingFetch = null
      })

    return pendingFetch
  }

  // Logging in or out changes which cart the API serves: at login the backend
  // moves the guest cart into the account (possibly replacing the saved one,
  // which fetchCart() reports as a notice), and after logout it's the empty guest cart.
  function refreshCart() {
    return useCartStore().fetchCart().catch(() => null)
  }

  async function login(payload: LoginPayload) {
    const response = await useApi()<{ data: User }>('/login', { method: 'POST', body: payload })
    setUser(response.data)
    await refreshCart()
  }

  async function register(payload: RegisterPayload) {
    const response = await useApi()<{ data: User }>('/register', { method: 'POST', body: payload })
    setUser(response.data)
    await refreshCart()
  }

  async function logout() {
    try {
      await useApi()('/logout', { method: 'POST' })
    }
    catch (error) {
      // Already logged out server-side (e.g. the session expired): nothing to undo.
      if (apiStatus(error) !== 401) {
        throw error
      }
    }
    setUser(null)
    useCartStore().reset()
    await refreshCart()
  }

  return { user, resolved, isAuthenticated, isBuyer, fetchUser, login, register, logout }
})
