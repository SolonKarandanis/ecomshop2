import { beforeEach, describe, expect, it } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { readBody, setResponseStatus } from 'h3'
import CheckoutPage from '~/pages/checkout.vue'
import { useCartStore } from '~/stores/cart'
import type { SavedAddress } from '~/types/profile'

type Wrapper = Awaited<ReturnType<typeof mountSuspended>>

let checkoutBodies: Record<string, unknown>[]
let savedAddresses: SavedAddress[]

beforeEach(() => {
  checkoutBodies = []
  savedAddresses = []
  document.cookie = 'XSRF-TOKEN=token; path=/'
  clearNuxtData()

  registerEndpoint('/api/payment-methods', () => ({
    data: [
      { id: 1, key: 'payment.method.cod', label: 'Cash on Delivery' },
      { id: 2, key: 'payment.method.stripe', label: 'Stripe' },
    ],
  }))
  registerEndpoint('/api/profile/addresses', () => ({ data: savedAddresses }))
  registerEndpoint('/api/checkout', {
    method: 'POST',
    handler: async (event) => {
      const body = await readBody(event)
      checkoutBodies.push(body)
      // Laravel's 422 body as-is (createError would wrap it in h3's own error shape).
      setResponseStatus(event, 422)
      return { message: 'The phone field is invalid.', errors: { phone: ['The phone field is invalid.'] } }
    },
  })

  // The page reads the Nuxt app's own Pinia, so fill that cart directly.
  const cart = useCartStore()
  cart.items = [{ id: '1', product_id: 3, product_name: 'Mug', product_slug: 'mug', quantity: 2, unit_price: 5, total_price: 10, attributes: {} }]
  cart.totalPrice = 10
  cart.loaded = true
})

const fields = ['firstName', 'lastName', 'phone', 'address', 'city', 'zipCode', 'country'] as const

describe('checkout page', () => {
  it('lists the payment methods by label, with the first one chosen', async () => {
    const page = await mountSuspended(CheckoutPage)

    expect(page.text()).toContain('Cash on Delivery')
    expect(page.text()).toContain('Stripe')
    const checked = page.find<HTMLInputElement>('input[type="radio"][value="payment.method.cod"], button[role="radio"][aria-checked="true"]')
    expect(checked.exists()).toBe(true)
  })

  it('requires every shipping field before calling the API', async () => {
    const page = await mountSuspended(CheckoutPage)

    await page.find('form').trigger('submit')
    await flushPromises()

    expect(checkoutBodies).toHaveLength(0)
    expect(page.text()).toContain('Enter your first name.')
    expect(page.text()).toContain('Enter your postal code.')
  })

  it('sends the form with the chosen method key and shows a 422 next to its field', async () => {
    const page = await mountSuspended(CheckoutPage)

    const inputs = page.findAll('input[autocomplete]')
    expect(inputs).toHaveLength(fields.length)
    for (const input of inputs) {
      await input.setValue('Something')
    }
    await page.find('form').trigger('submit')
    await flushPromises()
    await flushPromises()

    expect(checkoutBodies).toEqual([{
      firstName: 'Something',
      lastName: 'Something',
      phone: 'Something',
      address: 'Something',
      city: 'Something',
      zipCode: 'Something',
      country: 'Something',
      paymentMethod: 'payment.method.cod',
      saveAddress: false,
    }])
    expect(page.text()).toContain('The phone field is invalid.')
  })

  it('sends saveAddress when "Save this address" is ticked', async () => {
    const page = await mountSuspended(CheckoutPage)

    for (const input of page.findAll('input[autocomplete]')) {
      await input.setValue('Something')
    }
    await page.find('button[role="checkbox"]').trigger('click')
    await page.find('form').trigger('submit')
    await flushPromises()
    await flushPromises()

    expect(checkoutBodies).toHaveLength(1)
    expect(checkoutBodies[0]).toMatchObject({ saveAddress: true })
  })
})

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
    is_default: false,
    ...overrides,
  }
}

function inputValue(page: Wrapper, autocomplete: string) {
  return page.find<HTMLInputElement>(`input[autocomplete="${autocomplete}"]`).element.value
}

describe('checkout saved-address picker', () => {
  it('hides the picker when the address book is empty', async () => {
    const page = await mountSuspended(CheckoutPage)

    expect(page.text()).not.toContain('Saved addresses')
    expect(inputValue(page, 'street-address')).toBe('')
  })

  it('prefills the shipping form from the default saved address', async () => {
    savedAddresses = [
      savedAddress({ id: 1, street_address: '2 Side St', city: 'Sparta' }),
      savedAddress({ id: 2, is_default: true }),
    ]
    const page = await mountSuspended(CheckoutPage)

    expect(page.text()).toContain('Saved addresses')
    expect(inputValue(page, 'given-name')).toBe('Jane')
    expect(inputValue(page, 'street-address')).toBe('1 Main St')
    expect(inputValue(page, 'address-level2')).toBe('Athens')
    expect(inputValue(page, 'postal-code')).toBe('12345')
    expect(inputValue(page, 'country-name')).toBe('Greece')
  })

  it('fills the form from the address picked, and keeps the fields editable', async () => {
    savedAddresses = [
      savedAddress({ id: 1, is_default: true }),
      savedAddress({ id: 2, first_name: 'John', street_address: '2 Side St', city: 'Sparta', postal_code: '23100' }),
    ]
    const page = await mountSuspended(CheckoutPage)

    // Reka's Select opens from the keyboard on its trigger and renders its options in a portal.
    await page.find('button[role="combobox"]').trigger('keydown', { key: 'Enter' })
    await flushPromises()
    const option = [...document.body.querySelectorAll<HTMLElement>('[role="option"]')].find(item => item.textContent?.includes('2 Side St'))!
    option.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }))
    await flushPromises()

    expect(inputValue(page, 'given-name')).toBe('John')
    expect(inputValue(page, 'street-address')).toBe('2 Side St')
    expect(inputValue(page, 'address-level2')).toBe('Sparta')
    expect(inputValue(page, 'postal-code')).toBe('23100')

    await page.find('input[autocomplete="address-level2"]').setValue('Kalamata')
    await page.find('form').trigger('submit')
    await flushPromises()
    await flushPromises()

    expect(checkoutBodies[0]).toMatchObject({ firstName: 'John', address: '2 Side St', city: 'Kalamata', zipCode: '23100' })
  })
})
