import { beforeEach, describe, expect, it } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { readBody, setResponseStatus } from 'h3'
import CheckoutPage from '~/pages/checkout.vue'
import { useCartStore } from '~/stores/cart'

let checkoutBodies: Record<string, unknown>[]

beforeEach(() => {
  checkoutBodies = []
  document.cookie = 'XSRF-TOKEN=token; path=/'
  clearNuxtData()

  registerEndpoint('/api/payment-methods', () => ({
    data: [
      { id: 1, key: 'payment.method.cod', label: 'Cash on Delivery' },
      { id: 2, key: 'payment.method.stripe', label: 'Stripe' },
    ],
  }))
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
    }])
    expect(page.text()).toContain('The phone field is invalid.')
  })
})
