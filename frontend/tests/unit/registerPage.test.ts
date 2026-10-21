import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mockNuxtImport, mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { readBody } from 'h3'
import RegisterPage from '~/pages/register.vue'

const { navigateToMock } = vi.hoisted(() => ({ navigateToMock: vi.fn() }))
mockNuxtImport('navigateTo', () => navigateToMock)

let suppliersEnabled: boolean
let registerBodies: Record<string, unknown>[]

beforeEach(() => {
  suppliersEnabled = false
  registerBodies = []
  navigateToMock.mockClear()
  document.cookie = 'XSRF-TOKEN=token; path=/'
  clearNuxtData()
  // The page uses the Nuxt app's own Pinia, which outlives each test.
  useAuthStore().user = null

  registerEndpoint('/api/config', () => ({ data: { suppliers_enabled: suppliersEnabled } }))
  registerEndpoint('/api/cart', () => ({ data: { total_price: 0, items: [], notice: null } }))
  registerEndpoint('/api/register', {
    method: 'POST',
    handler: async (event) => {
      const body = await readBody(event)
      registerBodies.push(body)
      const supplier = body.role === 'role.supplier'
      return {
        data: {
          id: 1,
          name: body.name,
          email: body.email,
          status: supplier ? 'user.status.pending' : 'user.status.active',
          roles: [supplier ? 'role.supplier' : 'role.buyer'],
          email_verified_at: null,
          created_at: null,
        },
      }
    },
  })
})

async function fillAndSubmit(page: Awaited<ReturnType<typeof mountSuspended>>) {
  await page.find('input[autocomplete="name"]').setValue('Sam')
  await page.find('input[autocomplete="email"]').setValue('sam@example.com')
  for (const input of page.findAll('input[autocomplete="new-password"]')) {
    await input.setValue('password123')
  }
  await page.find('form').trigger('submit')
  await flushPromises()
  await flushPromises()
}

describe('register page', () => {
  it('shows no account-type choice and sends no role while the Suppliers feature is off', async () => {
    const page = await mountSuspended(RegisterPage)

    expect(page.text()).not.toContain('Account type')

    await fillAndSubmit(page)

    // A Buyer is logged in and sent on once the cart has been refetched.
    await vi.waitFor(() => expect(navigateToMock).toHaveBeenCalled())
    expect(registerBodies).toEqual([{ name: 'Sam', email: 'sam@example.com', password: 'password123' }])
  })

  it('offers Buyer or Supplier while the feature is on, defaulting to Buyer', async () => {
    suppliersEnabled = true
    const page = await mountSuspended(RegisterPage)

    expect(page.text()).toContain('Account type')
    expect(page.text()).toContain('I want to sell (Supplier)')

    await fillAndSubmit(page)

    await vi.waitFor(() => expect(navigateToMock).toHaveBeenCalled())
    expect(registerBodies[0]).toMatchObject({ role: 'role.buyer' })
    expect(page.text()).not.toContain('awaiting approval')
  })

  it('shows the awaiting-approval screen instead of logging a Supplier in', async () => {
    suppliersEnabled = true
    const page = await mountSuspended(RegisterPage)

    await page.find('button[role="radio"][value="role.supplier"], input[type="radio"][value="role.supplier"]').trigger('click')
    await fillAndSubmit(page)

    await vi.waitFor(() => expect(page.text()).toContain('Your Supplier account is awaiting approval'))
    expect(registerBodies[0]).toMatchObject({ role: 'role.supplier' })
    expect(navigateToMock).not.toHaveBeenCalled()
    expect(useAuthStore().isAuthenticated).toBe(false)
  })
})
