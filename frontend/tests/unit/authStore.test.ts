import { beforeEach, describe, expect, it } from 'vitest'
import { registerEndpoint } from '@nuxt/test-utils/runtime'
import { createError, readBody } from 'h3'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '~/stores/auth'
import { useCartStore } from '~/stores/cart'

const buyer = {
  id: 7,
  name: 'Ada Buyer',
  email: 'ada@example.com',
  status: 'user.status.active',
  roles: ['role.buyer'],
  email_verified_at: null,
  created_at: null,
}

let session: typeof buyer | null
let userRequests: number
// The cart the API serves: the Buyer's saved cart while logged in, else the (empty) guest cart.
const savedCartItem = { id: '1', product_id: 3, product_name: 'Mug', product_slug: 'mug', quantity: 2, unit_price: 5, total_price: 10, attributes: {} }

beforeEach(() => {
  setActivePinia(createPinia())
  session = null
  userRequests = 0
  document.cookie = 'XSRF-TOKEN=token; path=/'

  const unauthenticated = () => createError({ statusCode: 401, data: { message: 'Unauthenticated.' } })

  registerEndpoint('/api/user', () => {
    userRequests++
    if (!session) throw unauthenticated()
    return { data: session }
  })
  registerEndpoint('/api/login', {
    method: 'POST',
    handler: async (event) => {
      const body = await readBody(event)
      if (body.password !== 'secret') {
        throw createError({
          statusCode: 422,
          data: { message: 'These credentials do not match our records.', errors: { email: ['These credentials do not match our records.'] } },
        })
      }
      session = buyer
      return { data: buyer }
    },
  })
  registerEndpoint('/api/register', {
    method: 'POST',
    handler: async (event) => {
      const body = await readBody(event)
      // A Supplier is created PENDING and not logged in.
      if (body.role === 'role.supplier') {
        return { data: { ...buyer, name: body.name, email: body.email, status: 'user.status.pending', roles: ['role.supplier'] } }
      }
      session = { ...buyer, ...body }
      return { data: session }
    },
  })
  registerEndpoint('/api/cart', () => ({
    data: session ? { total_price: 10, items: [savedCartItem], notice: null } : { total_price: 0, items: [], notice: null },
  }))
  registerEndpoint('/api/logout', {
    method: 'POST',
    handler: () => {
      if (!session) throw unauthenticated()
      session = null
      return null
    },
  })
})

describe('auth store', () => {
  it('resolves a logged-out visitor to no user without throwing', async () => {
    const auth = useAuthStore()

    await expect(auth.fetchUser()).resolves.toBeNull()

    expect(auth.resolved).toBe(true)
    expect(auth.isAuthenticated).toBe(false)
  })

  it('shares one /user request between concurrent callers', async () => {
    session = buyer
    const auth = useAuthStore()

    await Promise.all([auth.fetchUser(), auth.fetchUser()])

    expect(userRequests).toBe(1)
    expect(auth.user?.email).toBe(buyer.email)
  })

  it('logs in and populates the user', async () => {
    const auth = useAuthStore()

    await auth.login({ email: buyer.email, password: 'secret' })

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.user?.id).toBe(buyer.id)
  })

  it('surfaces a failed login as the API error and stays logged out', async () => {
    const auth = useAuthStore()

    await expect(auth.login({ email: buyer.email, password: 'wrong' })).rejects.toMatchObject({ statusCode: 422 })

    expect(auth.isAuthenticated).toBe(false)
  })

  it('registers and is logged in straight away', async () => {
    const auth = useAuthStore()

    await auth.register({ name: 'New Buyer', email: 'new@example.com', password: 'password123' })

    expect(auth.user?.email).toBe('new@example.com')
  })

  it('registers a Supplier as pending without logging them in', async () => {
    const auth = useAuthStore()

    const user = await auth.register({ name: 'Sam', email: 'sam@example.com', password: 'password123', role: 'role.supplier' })

    expect(user.status).toBe('user.status.pending')
    expect(auth.isAuthenticated).toBe(false)
    expect(useCartStore().loaded).toBe(false)
  })

  it('logs out, clearing the user, even if the session had already expired', async () => {
    session = buyer
    const auth = useAuthStore()
    await auth.fetchUser()

    session = null
    await auth.logout()

    expect(auth.user).toBeNull()
    expect(auth.resolved).toBe(true)
  })

  it('knows whether the user is a Buyer', async () => {
    const auth = useAuthStore()
    expect(auth.isBuyer).toBe(false)

    await auth.login({ email: buyer.email, password: 'secret' })
    expect(auth.isBuyer).toBe(true)

    auth.user = { ...buyer, roles: ['role.supplier'] }
    expect(auth.isBuyer).toBe(false)
  })

  it('knows whether the user is a Supplier', async () => {
    const auth = useAuthStore()
    expect(auth.isSupplier).toBe(false)

    await auth.login({ email: buyer.email, password: 'secret' })
    expect(auth.isSupplier).toBe(false)

    auth.user = { ...buyer, roles: ['role.supplier'] }
    expect(auth.isSupplier).toBe(true)
  })

  it('refetches the cart after logging in, since the API has just moved the guest cart into the account', async () => {
    const auth = useAuthStore()

    await auth.login({ email: buyer.email, password: 'secret' })

    expect(useCartStore().itemCount).toBe(2)
  })

  it('empties the cart on logout', async () => {
    const auth = useAuthStore()
    await auth.login({ email: buyer.email, password: 'secret' })

    await auth.logout()

    expect(useCartStore().itemCount).toBe(0)
  })
})
