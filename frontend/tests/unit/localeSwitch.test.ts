import { beforeEach, describe, expect, it } from 'vitest'
import { registerEndpoint } from '@nuxt/test-utils/runtime'
import { readBody } from 'h3'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '~/stores/auth'
import { useLocaleSwitch } from '~/composables/useLocaleSwitch'

const user = {
  id: 7,
  name: 'Ada Buyer',
  email: 'ada@example.com',
  locale: 'en',
  status: 'user.status.active',
  roles: ['role.buyer'],
  email_verified_at: null,
  created_at: null,
}

let patches: Record<string, unknown>[]

beforeEach(() => {
  setActivePinia(createPinia())
  patches = []
  document.cookie = 'XSRF-TOKEN=token; path=/'

  registerEndpoint('/api/profile', {
    method: 'PATCH',
    handler: async (event) => {
      patches.push(await readBody(event))
      return { data: {} }
    },
  })
})

describe('saveLocale', () => {
  it("saves the language on the user's profile, with their current name and email", async () => {
    const auth = useAuthStore()
    auth.user = { ...user }

    await auth.saveLocale('el')

    expect(patches).toEqual([{ name: 'Ada Buyer', email: 'ada@example.com', locale: 'el' }])
    expect(auth.user?.locale).toBe('el')
  })

  it('saves nothing for a guest, or when the language is already saved', async () => {
    const auth = useAuthStore()
    await auth.saveLocale('el')

    auth.user = { ...user, locale: 'el' }
    await auth.saveLocale('el')

    expect(patches).toEqual([])
  })
})

describe('useLocaleSwitch', () => {
  it('turns a path into the same page in another language', () => {
    const { pathInLocale } = useLocaleSwitch()

    expect(pathInLocale('/products', 'el')).toBe('/el/products')
    expect(pathInLocale('/el/products/blue-mug?tab=reviews', 'tr')).toBe('/tr/products/blue-mug?tab=reviews')
    expect(pathInLocale('/tr/orders/5', 'en')).toBe('/orders/5')
  })

  it("sends a user who logs in to their saved language, or leaves the path as it is", () => {
    const auth = useAuthStore()
    const { pathForUser } = useLocaleSwitch()

    auth.user = { ...user, locale: 'tr' }
    expect(pathForUser('/orders')).toBe('/tr/orders')

    auth.user = { ...user, locale: 'en' }
    expect(pathForUser('/orders')).toBe('/orders')
  })
})
