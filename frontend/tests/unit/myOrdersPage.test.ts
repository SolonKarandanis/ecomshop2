import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { getQuery, setResponseHeader, setResponseStatus } from 'h3'
import MyOrdersPage from '~/pages/orders/index.vue'
import MyOrderPage from '~/pages/orders/[id].vue'

type Wrapper = Awaited<ReturnType<typeof mountSuspended>>

function order(id: number, status: string, paymentStatus = 'order.payment.status.paid') {
  return { id, order_status: status, payment_status: paymentStatus, grand_total: 25, created_at: '2026-10-01T10:00:00Z' }
}

let listQueries: Record<string, unknown>[]
let listOrders: ReturnType<typeof order>[]
// Laravel's 400 body for the next export, if the export should be refused.
let exportRefusal: { message: string } | null
let downloads: { href: string, filename: string }[]

beforeEach(() => {
  listQueries = []
  listOrders = [order(3, 'order.status.delivered'), order(2, 'order.status.shipped'), order(1, 'order.status.draft', 'order.payment.status.pending')]
  exportRefusal = null
  downloads = []
  document.cookie = 'XSRF-TOKEN=token; path=/'
  clearNuxtData()

  // The detail tests navigate to their route, which runs the auth middleware.
  registerEndpoint('/api/user', () => ({ data: { id: 5, name: 'Sam', email: 'sam@example.com', status: 'user.status.active', roles: ['role.buyer'] } }))
  registerEndpoint('/api/orders', (event) => {
    listQueries.push(getQuery(event))
    return { data: listOrders, meta: { current_page: 1, last_page: 1, per_page: 20, total: listOrders.length } }
  })
  registerEndpoint('/api/orders/export', (event) => {
    if (exportRefusal) {
      setResponseStatus(event, 400)
      return exportRefusal
    }
    setResponseHeader(event, 'content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    return new Uint8Array([80, 75, 3, 4])
  })
  registerEndpoint('/api/orders/1', () => ({
    data: {
      ...order(1, 'order.status.shipped'),
      currency: 'eur',
      shipping_amount: 5,
      notes: null,
      payment_method: 'payment.method.stripe',
      address: { first_name: 'Jane', last_name: 'Doe', phone: '555-1234', street_address: '1 Main St', city: 'Athens', country: 'Greece', postal_code: '12345' },
      items: [{ id: 9, product_id: 3, product: { id: 3, name: 'Mug', slug: 'mug' }, quantity: 2, unit_amount: 10, total_amount: 20, attribute_labels: { Colour: 'Blue' } }],
      status_history: [
        { order_status: 'order.status.draft', payment_status: 'order.payment.status.pending', created_at: '2026-10-01T10:00:00Z' },
        { order_status: 'order.status.paid', payment_status: 'order.payment.status.paid', created_at: '2026-10-01T10:05:00Z' },
        { order_status: 'order.status.shipped', payment_status: 'order.payment.status.paid', created_at: '2026-10-02T09:30:00Z' },
      ],
    },
  }))
  registerEndpoint('/api/orders/98', (event) => {
    setResponseStatus(event, 404)
    return { message: 'Resource not found' }
  })
  registerEndpoint('/api/orders/99', (event) => {
    setResponseStatus(event, 403)
    return { message: 'This action is unauthorized.' }
  })

  // Record downloads instead of letting happy-dom follow the blob link.
  vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:orders')
  vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {})
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
    downloads.push({ href: this.href, filename: this.download })
  })
})

afterEach(() => {
  vi.restoreAllMocks()
})

function rows(page: Wrapper) {
  return page.findAll('tbody tr')
}

function button(page: Wrapper, label: string) {
  return page.findAll('button').find(candidate => candidate.text().trim() === label)!
}

async function settle() {
  await flushPromises()
  await flushPromises()
}

describe('my orders list', () => {
  it('lists the Buyer\'s orders in the API\'s order, with status badges and links', async () => {
    const page = await mountSuspended(MyOrdersPage, { route: '/orders' })

    expect(rows(page)).toHaveLength(3)
    expect(rows(page)[0]!.text()).toContain('#3')
    expect(rows(page)[0]!.text()).toContain('Delivered')
    expect(rows(page)[2]!.text()).toContain('Draft')
    expect(rows(page)[2]!.text()).toContain('Pending')
    expect(page.find('a[href="/orders/3"]').exists()).toBe(true)
    expect(listQueries[0]).toMatchObject({ page: '1', perPage: '20' })
  })

  it('explains when there are no orders, without an Export button', async () => {
    listOrders = []
    const page = await mountSuspended(MyOrdersPage, { route: '/orders' })

    expect(page.text()).toContain('You haven\'t placed any orders yet.')
    expect(button(page, 'Export')).toBeUndefined()
  })

  it('downloads orders.xlsx without leaving the page', async () => {
    const page = await mountSuspended(MyOrdersPage, { route: '/orders' })

    await button(page, 'Export').trigger('click')
    await settle()

    expect(URL.createObjectURL).toHaveBeenCalledWith(expect.any(Blob))
    expect(downloads).toEqual([{ href: 'blob:orders', filename: 'orders.xlsx' }])
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:orders')
    expect(page.find('[role="alert"]').exists()).toBe(false)
  })

  it('shows the API\'s message when the export is refused, and downloads nothing', async () => {
    exportRefusal = { message: 'You cannot export more than 10,000 orders at once. Current results: 12,345' }
    const page = await mountSuspended(MyOrdersPage, { route: '/orders' })

    await button(page, 'Export').trigger('click')
    await vi.waitFor(() => expect(page.text()).toContain('You cannot export more than 10,000 orders at once. Current results: 12,345'))
    expect(downloads).toHaveLength(0)
  })
})

describe('my order detail', () => {
  it('shows the order\'s items, address and payment', async () => {
    const page = await mountSuspended(MyOrderPage, { route: '/orders/1' })

    expect(page.text()).toContain('Order #1')
    expect(page.text()).toContain('Mug')
    expect(page.text()).toContain('Colour: Blue')
    expect(page.text()).toContain('1 Main St')
    expect(page.text()).toContain('Stripe')
  })

  it('shows the status history oldest first, with each payment status and time', async () => {
    const page = await mountSuspended(MyOrderPage, { route: '/orders/1' })

    const timeline = page.find('[aria-label="Status history"]')
    expect(timeline.exists()).toBe(true)
    const text = timeline.text()
    expect(text.indexOf('Draft')).toBeLessThan(text.indexOf('Paid'))
    expect(text.indexOf('Paid')).toBeLessThan(text.indexOf('Shipped'))
    expect(text).toContain('Payment: Pending')
    expect(text).toContain(formatDateTime('2026-10-02T09:30:00Z'))
  })

  it('shows a friendly forbidden state for another Buyer\'s order', async () => {
    const page = await mountSuspended(MyOrderPage, { route: '/orders/99' })

    expect(page.text()).toContain('This isn\'t your order')
    expect(page.find('a[href="/orders"]').exists()).toBe(true)
    expect(page.text()).not.toContain('This action is unauthorized.')
  })

  it('shows "Order not found" for an order that doesn\'t exist', async () => {
    const page = await mountSuspended(MyOrderPage, { route: '/orders/98' })

    expect(page.text()).toContain('Order not found')
  })
})
