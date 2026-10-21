import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, registerEndpoint } from '@nuxt/test-utils/runtime'
import { createError } from 'h3'
import { createPinia, setActivePinia } from 'pinia'
import type { RouteLocationNormalized } from 'vue-router'
import authMiddleware from '~/middleware/auth'
import guestMiddleware from '~/middleware/guest'
import supplierMiddleware from '~/middleware/supplier'

const { navigateToMock } = vi.hoisted(() => ({ navigateToMock: vi.fn((to: unknown) => to) }))
mockNuxtImport('navigateTo', () => navigateToMock)

const to = { fullPath: '/orders/5?tab=items' } as RouteLocationNormalized
let loggedIn: boolean
let roles: string[]

beforeEach(() => {
  setActivePinia(createPinia())
  navigateToMock.mockClear()
  loggedIn = false
  roles = ['role.buyer']

  registerEndpoint('/api/user', () => {
    if (!loggedIn) throw createError({ statusCode: 401 })
    return { data: { id: 1, name: 'Ada', email: 'ada@example.com', roles } }
  })
})

describe('auth middleware', () => {
  it('sends a logged-out visitor to /login, remembering where they were going', async () => {
    const result = await authMiddleware(to, to)

    expect(result).toEqual({ path: '/login', query: { redirect: '/orders/5?tab=items' } })
  })

  it('lets a logged-in user through', async () => {
    loggedIn = true

    expect(await authMiddleware(to, to)).toBeUndefined()
    expect(navigateToMock).not.toHaveBeenCalled()
  })
})

describe('guest middleware', () => {
  it('sends a logged-in user home', async () => {
    loggedIn = true

    expect(await guestMiddleware(to, to)).toBe('/')
  })

  it('lets a logged-out visitor through', async () => {
    expect(await guestMiddleware(to, to)).toBeUndefined()
  })
})

describe('supplier middleware', () => {
  it('lets a Supplier through', async () => {
    loggedIn = true
    roles = ['role.supplier']

    expect(await supplierMiddleware(to, to)).toBeUndefined()
  })

  it('sends anyone else home', async () => {
    loggedIn = true

    expect(await supplierMiddleware(to, to)).toBe('/')
  })
})
