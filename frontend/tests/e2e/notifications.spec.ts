import { expect, test } from '@playwright/test'
import { type FakeNotification, fakeApi } from './support/fakeApi'

function notification(id: string, message: string, orderId: number, read = false): FakeNotification {
  return {
    id,
    event_type: 'notification.order.shipped',
    order_id: orderId,
    url: `http://localhost:3000/orders/${orderId}`,
    order_url: `http://localhost:3000/orders/${orderId}`,
    message,
    read_at: read ? '2026-11-01T10:00:00.000000Z' : null,
    created_at: '2026-11-01T09:00:00.000000Z',
  }
}

test('the bell shows the unread count, and opening one marks it read and follows its link', async ({ page, context }) => {
  const api = await fakeApi(page, context)
  api.logIn()
  api.state.notifications = [
    notification('n2', 'Order #12 has shipped', 12),
    notification('n1', 'Order #11 has shipped', 11),
    notification('n0', 'Order #10 was delivered', 10, true),
  ]

  await page.goto('/')
  const bell = page.getByRole('button', { name: 'Notifications, 2 unread' })
  await expect(bell).toBeVisible()

  await bell.click()
  // Listing the unread ones in the dropdown marks nothing read.
  await expect(page.getByRole('button', { name: /Order #12 has shipped/ })).toBeVisible()
  expect(api.state.notifications.filter(n => !n.read_at)).toHaveLength(2)

  await page.getByRole('button', { name: /Order #12 has shipped/ }).click()

  await expect(page).toHaveURL('/orders/12')
  await expect(page.getByRole('button', { name: 'Notifications, 1 unread' })).toBeVisible()
  expect(api.state.notifications.find(n => n.id === 'n2')!.read_at).not.toBeNull()
})

test('the notifications page lists the history and marks everything read', async ({ page, context }) => {
  const api = await fakeApi(page, context)
  api.logIn()
  api.state.notifications = [
    notification('n2', 'Order #12 has shipped', 12),
    notification('n1', 'Order #11 has shipped', 11),
    notification('n0', 'Order #10 was delivered', 10, true),
  ]

  await page.goto('/notifications')

  const rows = page.getByRole('listitem')
  await expect(rows).toHaveText([/Order #12 has shipped/, /Order #11 has shipped/, /Order #10 was delivered/])
  await expect(page.locator('li[data-unread]')).toHaveCount(2)

  await page.getByRole('button', { name: 'Mark all as read' }).click()

  await expect(page.locator('li[data-unread]')).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Notifications, 0 unread' })).toBeVisible()
})

test('logging out removes the bell', async ({ page, context }) => {
  const api = await fakeApi(page, context)
  api.logIn()
  api.state.notifications = [notification('n1', 'Order #11 has shipped', 11)]

  await page.goto('/')
  await expect(page.getByRole('button', { name: 'Notifications, 1 unread' })).toBeVisible()

  await page.getByRole('button', { name: 'Log out' }).click()

  await expect(page.getByRole('link', { name: 'Log in' })).toBeVisible()
  await expect(page.getByRole('button', { name: /^Notifications/ })).toHaveCount(0)
})
