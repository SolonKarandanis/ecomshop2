import { beforeEach, describe, expect, it } from 'vitest'
import { registerEndpoint } from '@nuxt/test-utils/runtime'
import { createError, readBody } from 'h3'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '~/stores/auth'

const buyer = {
  id: 7,
  name: 'Ada Buyer',
  email: 'ada@example.com',
  status: 'active',
  roles: ['ROLE_BUYER'],
  email_verified_at: null,
  created_at: null,
}

let session: typeof buyer | null
let userRequests: number

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
      session = { ...buyer, ...(await readBody(event)) }
      return { data: session }
    },
  })
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

  it('logs out, clearing the user, even if the session had already expired', async () => {
    session = buyer
    const auth = useAuthStore()
    await auth.fetchUser()

    session = null
    await auth.logout()

    expect(auth.user).toBeNull()
    expect(auth.resolved).toBe(true)
  })
})
