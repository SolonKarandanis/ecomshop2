import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mockNuxtImport, mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { readBody, setResponseStatus } from 'h3'
import AddressesPage from '~/pages/profile/addresses.vue'
import SavedAddressForm from '~/components/SavedAddressForm.vue'
import type { SavedAddress } from '~/types/profile'

// Toasts render in the app's UApp, which mountSuspended doesn't mount.
const { toastAdd } = vi.hoisted(() => ({ toastAdd: vi.fn() }))
mockNuxtImport('useToast', () => () => ({ add: toastAdd }))

type Wrapper = Awaited<ReturnType<typeof mountSuspended>>

function savedAddress(overrides: Partial<SavedAddress> = {}): SavedAddress {
  return {
    id: 1,
    first_name: 'Jane',
    last_name: 'Doe',
    phone: '555-1234',
    street_address: '1 Main St',
    city: 'Athens',
    country: 'Greece',
    postal_code: '12345',
    is_default: true,
    ...overrides,
  }
}

let addresses: SavedAddress[]
let requests: { method: string, path: string, body?: unknown }[]
// Laravel's 422 `errors` for the next POST/PATCH, if any.
let validationErrors: Record<string, string[]> | null

beforeEach(() => {
  addresses = [
    savedAddress(),
    savedAddress({ id: 2, first_name: 'John', street_address: '2 Side St', city: 'Sparta', is_default: false }),
  ]
  requests = []
  validationErrors = null
  toastAdd.mockClear()
  document.cookie = 'XSRF-TOKEN=token; path=/'
  clearNuxtData()

  registerEndpoint('/api/profile/addresses', {
    method: 'GET',
    handler: () => ({ data: addresses }),
  })
  registerEndpoint('/api/profile/addresses', {
    method: 'POST',
    handler: async (event) => {
      const body = await readBody(event)
      requests.push({ method: 'POST', path: '/profile/addresses', body })
      if (validationErrors) {
        setResponseStatus(event, 422)
        return { message: 'The given data was invalid.', errors: validationErrors }
      }
      const created = { ...body, id: 3, is_default: addresses.length === 0 }
      addresses.push(created)
      setResponseStatus(event, 201)
      return { data: created }
    },
  })
  // One set of routes per id, as the other page tests do.
  for (const id of [1, 2, 3]) {
    registerEndpoint(`/api/profile/addresses/${id}`, {
      method: 'PATCH',
      handler: async (event) => {
        const body = await readBody(event)
        requests.push({ method: 'PATCH', path: `/profile/addresses/${id}`, body })
        const index = addresses.findIndex(address => address.id === id)
        addresses[index] = { ...addresses[index]!, ...body }
        return { data: addresses[index] }
      },
    })
    registerEndpoint(`/api/profile/addresses/${id}`, {
      method: 'DELETE',
      handler: (event) => {
        requests.push({ method: 'DELETE', path: `/profile/addresses/${id}` })
        addresses = addresses.filter(address => address.id !== id)
        if (addresses.length && !addresses.some(address => address.is_default)) {
          addresses[0]!.is_default = true
        }
        setResponseStatus(event, 204)
        return null
      },
    })
    registerEndpoint(`/api/profile/addresses/${id}/default`, {
      method: 'POST',
      handler: () => {
        requests.push({ method: 'POST', path: `/profile/addresses/${id}/default` })
        addresses = addresses
          .map(address => ({ ...address, is_default: address.id === id }))
          .sort((a, b) => Number(b.is_default) - Number(a.is_default))
        return { data: addresses[0] }
      },
    })
  }
})

function field(wrapper: Wrapper, label: string) {
  const match = wrapper.findAll('label').find(candidate => candidate.text().startsWith(label))
  if (!match) {
    throw new Error(`No field labelled ${label}`)
  }
  return {
    input: wrapper.find<HTMLInputElement>(`[id="${match.attributes('for')}"]`),
    text: () => match.element.closest('[data-slot="root"]')?.textContent ?? '',
  }
}

function button(wrapper: Wrapper, label: string, index = 0) {
  return wrapper.findAll('button').filter(candidate => candidate.text().trim() === label)[index]!
}

function addressCards(wrapper: Wrapper) {
  return wrapper.findAll('ul[aria-label="Saved addresses"] > li')
}

// UModal renders its content in a portal, outside the mounted wrapper.
function bodyButton(label: string) {
  return [...document.body.querySelectorAll('button')].find(candidate => candidate.textContent?.trim() === label)!
}

async function settle() {
  await flushPromises()
  await flushPromises()
}

describe('address book page', () => {
  it('lists the saved addresses with the default marked, and links it from the account nav', async () => {
    const page = await mountSuspended(AddressesPage, { route: '/profile/addresses' })

    const cards = addressCards(page)
    expect(cards).toHaveLength(2)
    expect(cards[0]!.text()).toContain('Jane Doe')
    expect(cards[0]!.text()).toContain('Default')
    expect(cards[1]!.text()).toContain('2 Side St')
    expect(cards[1]!.text()).not.toContain('Default')
    expect(page.find('a[href="/profile/addresses"]').exists()).toBe(true)
  })

  it('offers "Set as default" only on non-default addresses, and moves the default', async () => {
    const page = await mountSuspended(AddressesPage, { route: '/profile/addresses' })

    expect(addressCards(page)[0]!.text()).not.toContain('Set as default')
    await button(page, 'Set as default').trigger('click')
    await settle()

    expect(requests).toEqual([{ method: 'POST', path: '/profile/addresses/2/default' }])
    await vi.waitFor(() => expect(addressCards(page)[0]!.text()).toContain('John Doe'))
    expect(addressCards(page)[0]!.text()).toContain('Default')
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: 'Default address updated.', color: 'success' }))
  })

  it('deletes an address after confirming, and shows the promoted default', async () => {
    const page = await mountSuspended(AddressesPage, { route: '/profile/addresses' })

    await button(page, 'Delete', 0).trigger('click')
    await settle()
    expect(document.body.textContent).toContain('Delete this address?')
    expect(requests).toHaveLength(0)

    bodyButton('Delete address').click()
    await settle()

    expect(requests).toEqual([{ method: 'DELETE', path: '/profile/addresses/1' }])
    const cards = addressCards(page)
    expect(cards).toHaveLength(1)
    expect(cards[0]!.text()).toContain('John Doe')
    expect(cards[0]!.text()).toContain('Default')
  })

  it('explains how to add an address when the address book is empty', async () => {
    addresses = []
    const page = await mountSuspended(AddressesPage, { route: '/profile/addresses' })

    expect(addressCards(page)).toHaveLength(0)
    expect(page.text()).toContain('You haven\'t saved any addresses yet.')
  })

  it('opens the add form in a modal', async () => {
    const page = await mountSuspended(AddressesPage, { route: '/profile/addresses' })

    await button(page, 'Add address').trigger('click')
    await settle()

    expect(document.body.querySelector('[role="dialog"]')?.textContent).toContain('Street address')
  })
})

describe('saved address form', () => {
  it('requires every field before calling the API', async () => {
    const form = await mountSuspended(SavedAddressForm)

    await form.find('form').trigger('submit')
    await settle()

    expect(requests).toHaveLength(0)
    expect(form.text()).toContain('Enter the first name.')
    expect(form.text()).toContain('Enter the postal code.')
  })

  it('adds an address with the API\'s field names', async () => {
    const form = await mountSuspended(SavedAddressForm)

    for (const [label, value] of [['First name', 'Ann'], ['Last name', 'Lee'], ['Phone', '555-0000'], ['Street address', '3 High St'], ['City', 'Patras'], ['Postal code', '26221'], ['Country', 'Greece']] as const) {
      await field(form, label).input.setValue(value)
    }
    await form.find('form').trigger('submit')
    await settle()

    expect(requests).toEqual([{
      method: 'POST',
      path: '/profile/addresses',
      body: { first_name: 'Ann', last_name: 'Lee', phone: '555-0000', street_address: '3 High St', city: 'Patras', country: 'Greece', postal_code: '26221' },
    }])
    expect(form.emitted('saved')?.[0]?.[0]).toMatchObject({ id: 3, city: 'Patras' })
  })

  it('edits an existing address, prefilled', async () => {
    const form = await mountSuspended(SavedAddressForm, { props: { address: savedAddress({ id: 2, city: 'Sparta' }) } })

    expect(field(form, 'City').input.element.value).toBe('Sparta')
    await field(form, 'City').input.setValue('Kalamata')
    await form.find('form').trigger('submit')
    await settle()

    expect(requests).toHaveLength(1)
    expect(requests[0]).toMatchObject({ method: 'PATCH', path: '/profile/addresses/2', body: { city: 'Kalamata', street_address: '1 Main St' } })
    expect(form.emitted('saved')).toHaveLength(1)
  })

  it('shows a 422 next to its field', async () => {
    validationErrors = { postal_code: ['The postal code field must be at least 2 characters.'] }
    const form = await mountSuspended(SavedAddressForm)

    for (const label of ['First name', 'Last name', 'Phone', 'Street address', 'City', 'Postal code', 'Country']) {
      await field(form, label).input.setValue('Something')
    }
    await form.find('form').trigger('submit')
    await settle()

    expect(field(form, 'Postal code').text()).toContain('The postal code field must be at least 2 characters.')
    expect(form.emitted('saved')).toBeUndefined()
  })
})
