import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mockNuxtImport, mountSuspended, registerEndpoint } from '@nuxt/test-utils/runtime'
import { getQuery, getRouterParam, setResponseStatus } from 'h3'
import SupplierOrdersPage from '~/pages/supplier/orders/index.vue'
import SupplierOrderPage from '~/pages/supplier/orders/[id].vue'

// Toasts render in the app's UApp, which mountSuspended doesn't mount.
const { toastAdd } = vi.hoisted(() => ({ toastAdd: vi.fn() }))
mockNuxtImport('useToast', () => () => ({ add: toastAdd }))

function order(id: number, status: string) {
  return { id, order_status: status, payment_status: 'order.payment.status.paid', grand_total: 25, created_at: '2026-10-01T10:00:00Z' }
}

let listQueries: Record<string, unknown>[]
let actionCalls: string[]
// What the action endpoints answer: the updated Order, or Laravel's 400 for an illegal transition.
let actionFails: boolean

beforeEach(() => {
  listQueries = []
  actionCalls = []
  actionFails = false
  toastAdd.mockClear()
  document.cookie = 'XSRF-TOKEN=token; path=/'
  clearNuxtData()

  // The detail tests navigate to their route, which runs the auth and supplier middleware.
  registerEndpoint('/api/user', () => ({ data: { id: 5, name: 'Sam', email: 'sam@example.com', status: 'user.status.active', roles: ['role.supplier'] } }))
  registerEndpoint('/api/supplier-orders', (event) => {
    listQueries.push(getQuery(event))
    return {
      data: [order(1, 'order.status.paid'), order(2, 'order.status.shipped'), order(3, 'order.status.delivered')],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 3 },
    }
  })
  for (const action of ['ship', 'deliver', 'cancel']) {
    for (const id of [1, 2]) {
      registerEndpoint(`/api/supplier-orders/${id}/${action}`, {
        method: 'POST',
        handler: (event) => {
          actionCalls.push(`${getRouterParam(event, 'id') ?? id}/${action}`)
          if (actionFails) {
            setResponseStatus(event, 400)
            return { message: 'This action is not allowed for this order.' }
          }
          const target = { ship: 'order.status.shipped', deliver: 'order.status.delivered', cancel: 'order.status.cancelled' }[action]!
          return { data: order(id, target) }
        },
      })
    }
  }
  registerEndpoint('/api/orders/1', () => ({
    data: {
      ...order(1, 'order.status.paid'),
      currency: 'eur',
      shipping_amount: 5,
      notes: null,
      payment_method: 'payment.method.cod',
      address: { first_name: 'Jane', last_name: 'Doe', phone: '555-1234', street_address: '1 Main St', city: 'Athens', country: 'Greece', postal_code: '12345' },
      items: [{ id: 9, product_id: 3, product: { id: 3, name: 'Mug', slug: 'mug' }, quantity: 2, unit_amount: 10, total_amount: 20, attribute_labels: { Colour: 'Blue' } }],
    },
  }))
  registerEndpoint('/api/orders/99', (event) => {
    setResponseStatus(event, 403)
    return { message: 'This action is unauthorized.' }
  })
})

function rows(page: Awaited<ReturnType<typeof mountSuspended>>) {
  return page.findAll('tbody tr')
}

describe('supplier orders list', () => {
  it('lists the Orders with status badges, linking each to its detail page', async () => {
    const page = await mountSuspended(SupplierOrdersPage)

    expect(rows(page)).toHaveLength(3)
    expect(rows(page)[0]!.text()).toContain('Paid')
    expect(rows(page)[1]!.text()).toContain('Shipped')
    expect(page.find('a[href="/supplier/orders/2"]').exists()).toBe(true)
    expect(listQueries[0]).toMatchObject({ page: '1', perPage: '20' })
    expect(listQueries[0]).not.toHaveProperty('orderStatus')
  })

  it('only offers the actions that are valid for each status', async () => {
    const page = await mountSuspended(SupplierOrdersPage)
    const buttons = (row: number) => rows(page)[row]!.findAll('button').map(b => b.text())

    expect(buttons(0)).toEqual(['Ship', 'Cancel'])
    expect(buttons(1)).toEqual(['Mark delivered'])
    expect(buttons(2)).toEqual([])
  })

  it('ships an Order and updates its row in place', async () => {
    const page = await mountSuspended(SupplierOrdersPage)

    await rows(page)[0]!.find('button').trigger('click')

    // Optimistic: the row changes before the API answers.
    expect(rows(page)[0]!.text()).toContain('Shipped')
    await vi.waitFor(() => expect(actionCalls).toEqual(['1/ship']))
    await flushPromises()
    expect(rows(page)[0]!.text()).toContain('Shipped')
    expect(rows(page)[0]!.findAll('button').map(b => b.text())).toEqual(['Mark delivered'])
    expect(listQueries).toHaveLength(1)
  })

  it('puts the row back and shows the error when the API refuses', async () => {
    actionFails = true
    const page = await mountSuspended(SupplierOrdersPage)

    await rows(page)[1]!.find('button').trigger('click')
    expect(rows(page)[1]!.text()).toContain('Delivered')

    await vi.waitFor(() => expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ title: 'This action is not allowed for this order.', color: 'error' })))
    expect(actionCalls).toEqual(['2/deliver'])
    expect(rows(page)[1]!.text()).toContain('Shipped')
  })

  it('asks for confirmation before cancelling', async () => {
    const page = await mountSuspended(SupplierOrdersPage)

    await rows(page)[0]!.findAll('button')[1]!.trigger('click')
    await flushPromises()

    expect(actionCalls).toEqual([])
    expect(document.body.textContent).toContain('Cancel order #1?')

    const confirm = [...document.body.querySelectorAll('button')].find(b => b.textContent?.trim() === 'Cancel order')!
    confirm.click()

    await vi.waitFor(() => expect(actionCalls).toEqual(['1/cancel']))
    expect(rows(page)[0]!.text()).toContain('Cancelled')
  })
})

describe('supplier order detail', () => {
  it('shows the shipping address, line items with attributes, and totals', async () => {
    const page = await mountSuspended(SupplierOrderPage, { route: '/supplier/orders/1' })
    const text = page.text()

    expect(text).toContain('Order #1')
    expect(text).toContain('Jane Doe')
    expect(text).toContain('1 Main St')
    expect(text).toContain('12345 Athens')
    expect(text).toContain('555-1234')
    expect(text).toContain('Mug')
    expect(text).toContain('Colour: Blue')
    expect(text).toContain('Cash on Delivery')
    expect(page.findAll('button').map(b => b.text())).toEqual(['Ship', 'Cancel'])
  })

  it('shows a friendly state for another Supplier\'s Order', async () => {
    const page = await mountSuspended(SupplierOrderPage, { route: '/supplier/orders/99' })

    expect(page.text()).toContain('Order unavailable')
  })
})
