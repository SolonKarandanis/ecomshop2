import type { Paginated } from '~/types/catalogue'
import type { AppNotification } from '~/types/notification'

const PER_PAGE = 20

/**
 * The logged-in user's Notifications: the newest unread ones and the unread
 * count for the header bell, and a page of the full history for
 * /notifications. Marking read updates both from the API's new unread count,
 * so the bell and the page never disagree.
 */
export const useNotificationsStore = defineStore('notifications', () => {
  const items = ref<AppNotification[]>([])
  const meta = ref<Paginated<AppNotification>['meta'] | null>(null)
  const unread = ref<AppNotification[]>([])
  const unreadCount = ref(0)

  async function fetchAll(page = 1) {
    const response = await useApi()<Paginated<AppNotification>>('/notifications', { query: { page, per_page: PER_PAGE } })
    items.value = response.data
    meta.value = response.meta
  }

  // The API's five newest unread. Read-only: listing them marks nothing read.
  async function fetchUnread() {
    const response = await useApi()<{ data: AppNotification[] }>('/notifications/unread')
    unread.value = response.data
  }

  async function fetchUnreadCount() {
    const response = await useApi()<{ count: number }>('/notifications/unread-count')
    unreadCount.value = response.count
  }

  function setRead(isRead: (notification: AppNotification) => boolean) {
    const now = new Date().toISOString()
    items.value = items.value.map(n => isRead(n) && !n.read_at ? { ...n, read_at: now } : n)
    unread.value = unread.value.filter(n => !isRead(n))
  }

  async function markRead(ids: string[]) {
    if (ids.length === 0) {
      return
    }
    const response = await useApi()<{ count: number }>('/notifications/mark-read', { method: 'POST', body: { ids } })
    unreadCount.value = response.count
    setRead(n => ids.includes(n.id))
  }

  async function markAllRead() {
    const response = await useApi()<{ count: number }>('/notifications/mark-all-read', { method: 'POST' })
    unreadCount.value = response.count
    setRead(() => true)
  }

  function reset() {
    items.value = []
    meta.value = null
    unread.value = []
    unreadCount.value = 0
  }

  return { items, meta, unread, unreadCount, fetchAll, fetchUnread, fetchUnreadCount, markRead, markAllRead, reset }
})
