import { describe, expect, it } from 'vitest'
import type { AppNotification } from '~/types/notification'
import { notificationLink, type NotificationLinkContext } from '~/utils/notificationLink'

const buyer: NotificationLinkContext = {
  siteOrigin: 'https://solonkarandanis.shop',
  apiBase: 'https://api.solonkarandanis.shop',
  isAdmin: false,
  isSupplier: false,
  localize: path => `/el${path}`,
}
const supplier = { ...buyer, isSupplier: true }
const admin = { ...buyer, isAdmin: true }

function notification(fields: Partial<AppNotification>): AppNotification {
  return { id: '1', event_type: null, order_id: 7, url: null, order_url: null, message: null, read_at: null, created_at: null, ...fields }
}

describe('notificationLink', () => {
  it('navigates inside the storefront to its own URL, keeping the language it was sent in', () => {
    expect(notificationLink(notification({ url: 'https://solonkarandanis.shop/tr/orders/7' }), buyer))
      .toEqual({ to: '/tr/orders/7', external: false })
  })

  it('leaves the storefront for a link elsewhere, like the Filament panel', () => {
    expect(notificationLink(notification({ url: 'https://api.solonkarandanis.shop/admin/users/3' }), admin))
      .toEqual({ to: 'https://api.solonkarandanis.shop/admin/users/3', external: true })
  })

  it('falls back to order_url when there is no url', () => {
    expect(notificationLink(notification({ order_url: 'https://solonkarandanis.shop/orders/7' }), buyer))
      .toEqual({ to: '/orders/7', external: false })
  })

  it.each([
    ['a Buyer', buyer, { to: '/el/orders/7', external: false }],
    ['a Supplier', supplier, { to: '/el/supplier/orders/7', external: false }],
    ['an admin', admin, { to: 'https://api.solonkarandanis.shop/admin/orders/7', external: true }],
  ])('rebuilds an old /api/ order link for %s from order_id', (_who, context, expected) => {
    const old = notification({ url: 'https://api.solonkarandanis.shop/api/orders/7', order_url: 'https://api.solonkarandanis.shop/api/orders/7' })

    expect(notificationLink(old, context)).toEqual(expected)
  })

  it('has no link without a usable URL or an order', () => {
    expect(notificationLink(notification({ url: 'https://api.solonkarandanis.shop/api/x', order_id: null }), buyer)).toBeNull()
    expect(notificationLink(notification({ order_id: null }), buyer)).toBeNull()
  })
})
