import { beforeEach, describe, expect, it } from 'vitest'
import { registerEndpoint } from '@nuxt/test-utils/runtime'
import { getQuery, readBody } from 'h3'
import { createPinia, setActivePinia } from 'pinia'
import { useNotificationsStore } from '~/stores/notifications'
import type { AppNotification } from '~/types/notification'

// A fake of the API's Notifications for one user, newest first.
let stored: AppNotification[]
let lastQuery: Record<string, unknown>

function notification(id: string, read = false): AppNotification {
  return {
    id,
    event_type: 'notification.order.shipped',
    order_id: 1,
    url: 'http://localhost:3000/orders/1',
    order_url: 'http://localhost:3000/orders/1',
    message: `Notification ${id}`,
    read_at: read ? '2026-11-01T10:00:00.000000Z' : null,
    created_at: '2026-11-01T09:00:00.000000Z',
  }
}

const unreadCount = () => stored.filter(n => !n.read_at).length

beforeEach(() => {
  setActivePinia(createPinia())
  stored = [notification('a'), notification('b'), notification('c', true)]
  document.cookie = 'XSRF-TOKEN=token; path=/'

  registerEndpoint('/api/notifications', (event) => {
    lastQuery = getQuery(event)
    return { data: stored, meta: { current_page: 1, last_page: 1, per_page: 20, total: stored.length } }
  })
  registerEndpoint('/api/notifications/unread', () => ({ data: stored.filter(n => !n.read_at) }))
  registerEndpoint('/api/notifications/unread-count', () => ({ count: unreadCount() }))
  registerEndpoint('/api/notifications/mark-read', {
    method: 'POST',
    handler: async (event) => {
      const { ids } = await readBody<{ ids: string[] }>(event)
      stored = stored.map(n => ids.includes(n.id) && !n.read_at ? { ...n, read_at: '2026-11-02T12:00:00.000000Z' } : n)
      return { count: unreadCount() }
    },
  })
  registerEndpoint('/api/notifications/mark-all-read', {
    method: 'POST',
    handler: () => {
      stored = stored.map(n => ({ ...n, read_at: n.read_at ?? '2026-11-02T12:00:00.000000Z' }))
      return { count: 0 }
    },
  })
})

describe('notifications store', () => {
  it('loads the history page, the unread list and the unread count', async () => {
    const notifications = useNotificationsStore()

    await Promise.all([notifications.fetchAll(2), notifications.fetchUnread(), notifications.fetchUnreadCount()])

    expect(notifications.items.map(n => n.id)).toEqual(['a', 'b', 'c'])
    expect(lastQuery).toEqual({ page: '2', per_page: '20' })
    expect(notifications.unread.map(n => n.id)).toEqual(['a', 'b'])
    expect(notifications.unreadCount).toBe(2)
  })

  it('marks one read: the count comes from the API, the history shows it read, and the bell drops it', async () => {
    const notifications = useNotificationsStore()
    await Promise.all([notifications.fetchAll(), notifications.fetchUnread(), notifications.fetchUnreadCount()])

    await notifications.markRead(['a'])

    expect(notifications.unreadCount).toBe(1)
    expect(notifications.items.find(n => n.id === 'a')!.read_at).not.toBeNull()
    expect(notifications.items.find(n => n.id === 'b')!.read_at).toBeNull()
    expect(notifications.unread.map(n => n.id)).toEqual(['b'])
  })

  it('keeps the original read time of one that was already read', async () => {
    const notifications = useNotificationsStore()
    await notifications.fetchAll()

    await notifications.markRead(['c'])

    expect(notifications.items.find(n => n.id === 'c')!.read_at).toBe('2026-11-01T10:00:00.000000Z')
  })

  it('sends nothing for an empty list', async () => {
    const notifications = useNotificationsStore()
    notifications.unreadCount = 5

    await notifications.markRead([])

    expect(notifications.unreadCount).toBe(5)
  })

  it('marks all read: the count goes to 0 and every one shows as read', async () => {
    const notifications = useNotificationsStore()
    await Promise.all([notifications.fetchAll(), notifications.fetchUnread(), notifications.fetchUnreadCount()])

    await notifications.markAllRead()

    expect(notifications.unreadCount).toBe(0)
    expect(notifications.items.every(n => n.read_at !== null)).toBe(true)
    expect(notifications.unread).toEqual([])
  })

  it('forgets everything on reset, as at logout', async () => {
    const notifications = useNotificationsStore()
    await Promise.all([notifications.fetchAll(), notifications.fetchUnread(), notifications.fetchUnreadCount()])

    notifications.reset()

    expect(notifications.items).toEqual([])
    expect(notifications.unread).toEqual([])
    expect(notifications.unreadCount).toBe(0)
    expect(notifications.meta).toBeNull()
  })
})
